<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CampaignExportService
{
    public function export(Campaign $campaign, string $kind, array $rows, string $format): string
    {
        $format = strtolower($format);

        return match ($format) {
            'pdf' => $this->pdf($campaign, $kind, $rows),
            default => $this->xlsx($campaign, $kind, $rows),
        };
    }

    private function xlsx(Campaign $campaign, string $kind, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit($this->title($kind), 31, ''));

        [$headers, $dataRows] = $this->table($kind, $rows);
        $sheet->fromArray([$this->title($kind).' - '.$campaign->title], null, 'A1');
        $sheet->fromArray($headers, null, 'A3');

        $rowNumber = 4;
        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        foreach (range('A', chr(ord('A') + max(count($headers) - 1, 0))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/campaign-'.$campaign->id.'-'.$kind.'-'.now()->format('YmdHis').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function pdf(Campaign $campaign, string $kind, array $rows): string
    {
        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/campaign-'.$campaign->id.'-'.$kind.'-'.now()->format('YmdHis').'.pdf';

        $html = $this->html($campaign, $kind, $rows);

        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape')->save($path);

            return $path;
        }

        File::put($path, $this->minimalPdf($campaign->title, $this->title($kind)));

        return $path;
    }

    private function html(Campaign $campaign, string $kind, array $rows): string
    {
        [$headers, $dataRows] = $this->table($kind, $rows);
        $head = collect($headers)->map(fn (string $header): string => '<th>'.e($header).'</th>')->implode('');
        $body = collect($dataRows)->map(fn (array $row): string => '<tr>'.collect($row)->map(fn ($cell): string => '<td>'.e((string) $cell).'</td>')->implode('').'</tr>')->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#172033}h1{font-size:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d9e1ea;padding:6px;text-align:left}th{background:#eef3f8}</style></head><body><h1>'
            .e($this->title($kind)).'</h1><p>'.e($campaign->title).'</p><table><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table></body></html>';
    }

    private function table(string $kind, array $rows): array
    {
        if ($kind === 'participants') {
            return [
                ['STT', 'Tên đăng ký', 'Loại', 'Lớp', 'Học sinh/Thành viên', 'Trạng thái', 'Người đăng ký', 'Người duyệt'],
                collect($rows)->values()->map(fn (array $row, int $index): array => [
                    $index + 1,
                    $row['participant_name'] ?? '',
                    $row['participant_type_label'] ?? $row['participant_type'] ?? '',
                    $row['class_name'] ?? '',
                    $this->membersText($row),
                    $row['status_label'] ?? $row['status'] ?? '',
                    $row['registered_by'] ?? '',
                    $row['approved_by'] ?? '',
                ])->all(),
            ];
        }

        return [
            ['STT', 'Tên đăng ký', 'Lớp', 'Tổng điểm', 'Xếp hạng', 'Giải thưởng', 'Điểm rèn luyện', 'Điểm thi đua lớp', 'Trạng thái'],
            collect($rows)->values()->map(fn (array $row, int $index): array => [
                $index + 1,
                $row['participant_name'] ?? '',
                $row['class_name'] ?? '',
                $row['total_score'] ?? '',
                $row['rank'] ?? '',
                $row['award_title'] ?? '',
                $row['conduct_points'] ?? '',
                $row['class_points'] ?? '',
                $row['status'] ?? '',
            ])->all(),
        ];
    }

    private function membersText(array $row): string
    {
        if (! empty($row['student_name'])) {
            return trim(($row['student_code'] ?? '').' '.$row['student_name']);
        }

        return collect($row['members'] ?? [])
            ->map(fn (array $member): string => trim(($member['student_code'] ?? '').' '.($member['full_name'] ?? '')))
            ->filter()
            ->implode(', ');
    }

    private function title(string $kind): string
    {
        return match ($kind) {
            'participants' => 'Danh sách tham gia',
            'rankings' => 'Xếp hạng phong trào',
            default => 'Kết quả phong trào',
        };
    }

    private function minimalPdf(string $campaignTitle, string $title): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($title.' - '.$campaignTitle));
        $stream = "BT /F1 16 Tf 72 760 Td ($text) Tj ET";
        $length = strlen($stream);

        return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj\n"
            ."4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
            ."5 0 obj << /Length $length >> stream\n$stream\nendstream endobj\n"
            ."xref\n0 6\n0000000000 65535 f \n"
            ."trailer << /Size 6 /Root 1 0 R >>\nstartxref\n0\n%%EOF";
    }
}
