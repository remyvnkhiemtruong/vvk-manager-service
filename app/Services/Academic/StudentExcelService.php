<?php

namespace App\Services\Academic;

use App\Models\ClassEnrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Support\Audit\Auditor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentExcelService
{
    public function __construct(private readonly AcademicService $academic)
    {
    }

    public function import(Request $request, UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'File import không có dữ liệu học sinh.']);
        }

        $headers = $this->headers(array_shift($rows));
        $created = 0;
        $updated = 0;
        $linked = 0;
        $skipped = [];

        DB::transaction(function () use ($request, $rows, $headers, &$created, &$updated, &$linked, &$skipped): void {
            foreach ($rows as $index => $row) {
                $data = $this->rowData($headers, $row);

                if ($this->blank($data)) {
                    continue;
                }

                if (blank($data['student_code'] ?? null) || blank($data['full_name'] ?? null)) {
                    $skipped[] = ['row' => $index + 2, 'reason' => 'Thiếu mã học sinh hoặc họ tên.'];
                    continue;
                }

                $studentData = [
                    'student_code' => trim((string) $data['student_code']),
                    'full_name' => trim((string) $data['full_name']),
                    'gender' => $data['gender'] ?: 'other',
                    'birth_date' => $this->dateValue($data['birth_date'] ?? null),
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'status' => 'active',
                ];

                $student = Student::where('student_code', $studentData['student_code'])->first();

                if ($student) {
                    $this->academic->updateStudent($request, $student, $studentData);
                    $updated++;
                } else {
                    $student = $this->academic->createStudent($request, $studentData);
                    $created++;
                }

                if (! blank($data['class_code'] ?? null)) {
                    $this->importEnrollment($request, $student, trim((string) $data['class_code']));
                }

                if (! blank($data['guardian_name'] ?? null)) {
                    $guardian = $this->findOrCreateGuardian($request, $data);
                    $this->academic->linkGuardian($request, $student, [
                        'guardian_id' => $guardian->id,
                        'relationship' => $data['guardian_relationship'] ?? 'Cha/Mẹ',
                        'is_primary' => ! $student->guardians()->exists(),
                    ]);
                    $linked++;
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'linked_guardians' => $linked,
            'skipped' => $skipped,
        ];
    }

    public function exportClass(Request $request, SchoolClass $class): string
    {
        $students = $this->academic->classStudents($request, $class);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh sach hoc sinh');

        $headers = ['STT', 'Mã học sinh', 'Họ tên', 'Giới tính', 'Ngày sinh', 'Điện thoại', 'Email', 'Địa chỉ', 'Trạng thái'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($students as $index => $student) {
            $sheet->fromArray([
                $index + 1,
                $student->student_code,
                $student->full_name,
                $student->gender,
                $student->birth_date?->format('d/m/Y'),
                $student->phone,
                $student->email,
                $student->address,
                $student->status,
            ], null, 'A'.$row);
            $row++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/students-'.$class->id.'-'.now()->format('YmdHis').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function importEnrollment(Request $request, Student $student, string $classCode): void
    {
        $class = SchoolClass::query()
            ->where('code', $classCode)
            ->orWhere('name', $classCode)
            ->first();

        if (! $class) {
            return;
        }

        $schoolYear = SchoolYear::query()->where('is_active', true)->first()
            ?? SchoolYear::query()->latest('id')->first();
        $semester = Semester::query()->where('is_active', true)->first()
            ?? Semester::query()->where('school_year_id', $schoolYear?->id)->orderByDesc('term_number')->first();

        if (! $schoolYear || ! $semester) {
            return;
        }

        $this->academic->enrollStudent($request, $student, [
            'class_id' => $class->id,
            'school_year_id' => $schoolYear->id,
            'semester_id' => $semester->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
            'note' => 'Import Excel',
        ]);
    }

    private function findOrCreateGuardian(Request $request, array $data): Guardian
    {
        $query = Guardian::query();

        if (! blank($data['guardian_email'] ?? null)) {
            $query->where('email', $data['guardian_email']);
        } elseif (! blank($data['guardian_phone'] ?? null)) {
            $query->where('phone', $data['guardian_phone']);
        } else {
            $query->where('full_name', $data['guardian_name']);
        }

        $guardian = $query->first();

        if ($guardian) {
            return $guardian;
        }

        $guardian = Guardian::create([
            'full_name' => $data['guardian_name'],
            'phone' => $data['guardian_phone'] ?? null,
            'email' => $data['guardian_email'] ?? null,
            'relationship' => $data['guardian_relationship'] ?? 'Cha/Mẹ',
            'status' => 'active',
        ]);

        Auditor::record('guardians.created', $guardian, null, $guardian->fresh()->getAttributes(), $request);

        return $guardian;
    }

    private function headers(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $headers[$column] = Str::of((string) $value)->trim()->lower()->snake()->value();
        }

        return $headers;
    }

    private function rowData(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $column => $key) {
            if ($key !== '') {
                $data[$key] = is_string($row[$column] ?? null) ? trim($row[$column]) : ($row[$column] ?? null);
            }
        }

        return $data;
    }

    private function blank(array $data): bool
    {
        return collect($data)->filter(fn ($value): bool => filled($value))->isEmpty();
    }

    private function dateValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
