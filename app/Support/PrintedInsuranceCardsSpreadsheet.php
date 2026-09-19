<?php

namespace App\Support;

use App\Models\Employee;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintedInsuranceCardsSpreadsheet
{
    /**
     * @return list<array{name: string, role: string}>
     */
    public static function rows(): array
    {
        return Employee::query()
            ->whereNotNull('card_printed_at')
            ->with([
                'latestSubmittedRegistration',
                'latestMedicalRegistration',
            ])
            ->orderBy('employee_number')
            ->orderBy('id')
            ->get()
            ->map(function (Employee $employee): array {
                $registration = $employee->latestSubmittedRegistration
                    ?? $employee->latestMedicalRegistration;

                return [
                    'name' => $employee->full_name ?: ($registration?->full_name ?: '—'),
                    'role' => 'موظف',
                ];
            })
            ->values()
            ->all();
    }

    public static function binary(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('البطاقات المطبوعة');
        $sheet->setRightToLeft(true);
        $sheet->setCellValue('A1', 'الاسم');
        $sheet->setCellValue('B1', 'الصفة');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);

        $rowNumber = 2;

        foreach (self::rows() as $row) {
            $sheet->setCellValue("A{$rowNumber}", $row['name']);
            $sheet->setCellValue("B{$rowNumber}", $row['role']);
            $sheet->getStyle("A{$rowNumber}:B{$rowNumber}")->getFont()->setBold(true);
            $rowNumber++;
        }

        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(16);

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    public static function download(): StreamedResponse
    {
        $binary = self::binary();
        $filename = 'printed-insurance-cards-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(
            function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
