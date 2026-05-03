<?php

namespace App\Services\Assessment;

use App\Models\ScoreColumn;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ScoreExcelService
{
    public function __construct(private readonly ScorebookService $scorebooks)
    {
    }

    public function import(Request $request, UploadedFile $file, array $filters, string $reason): array
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['revision_reason' => 'Can nhap ly do khi import cap nhat diem.']);
        }

        $scorebook = $this->scorebooks->scorebook($request, $filters);
        $columns = collect($scorebook['columns'])->keyBy(fn (array $column): string => Str::upper((string) $column['code']));
        $students = collect($scorebook['students'])->keyBy(fn (array $student): string => Str::upper((string) $student['student_code']));

        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'File import khong co du lieu diem.']);
        }

        $headers = $this->headers(array_shift($rows));
        $scoreRows = [];
        $skipped = [];

        foreach ($rows as $index => $row) {
            $data = $this->rowData($headers, $row);

            if ($this->blank($data)) {
                continue;
            }

            $studentCode = Str::upper(trim((string) ($data['student_code'] ?? '')));
            $student = $students[$studentCode] ?? null;

            if (! $student) {
                $skipped[] = ['row' => $index + 2, 'reason' => 'Khong tim thay hoc sinh trong lop.'];
                continue;
            }

            foreach ($columns as $code => $column) {
                $key = Str::lower($code);

                if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                    continue;
                }

                $scoreRows[] = [
                    'student_id' => $student['id'],
                    'score_column_id' => $column['id'],
                    $column['input_type'] === 'comment' ? 'comment' : 'score' => $data[$key],
                ];
            }
        }

        $result = $scoreRows
            ? $this->scorebooks->bulkUpsert($request, [
                ...$filters,
                'scores' => $scoreRows,
                'revision_reason' => $reason,
            ], 'excel_import')
            : ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        return [...$result, 'skipped' => $skipped];
    }

    public function export(Request $request, array $filters): string
    {
        $scorebook = $this->scorebooks->scorebook($request, $filters);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bang diem');

        $headers = ['STT', 'Ma hoc sinh', 'Ho ten'];
        foreach ($scorebook['columns'] as $column) {
            $headers[] = $column['code'];
        }
        $headers[] = 'Diem TB';
        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
        foreach ($scorebook['students'] as $index => $student) {
            $row = [$index + 1, $student['student_code'], $student['full_name']];

            foreach ($scorebook['columns'] as $column) {
                $score = $scorebook['scores'][$student['id']][$column['id']] ?? null;
                $row[] = $column['input_type'] === 'comment' ? ($score['comment'] ?? '') : ($score['score'] ?? '');
            }

            $row[] = $scorebook['averages'][$student['id']] ?? '';
            $sheet->fromArray($row, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $lastColumn = chr(ord('A') + count($headers) - 1);
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/scores-'.($filters['class_id'] ?? 'class').'-'.now()->format('YmdHis').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function headers(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $key = Str::of((string) $value)->trim()->lower()->snake()->value();
            $headers[$column] = $key;
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
}
