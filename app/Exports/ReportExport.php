<?php

namespace App\Exports;

use App\Models\App;
use App\Models\AppMetric;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportExport implements FromArray, WithEvents, WithTitle
{
    protected string $date;
    protected $apps;
    protected array $convRateCells = [];

    const COLOR_HEADER_DARK = 'FF2C3E50';
    const COLOR_HEADER_MID  = 'FF34495E';
    const COLOR_WHITE_ROW   = 'FFFFFFFF';
    const COLOR_ORANGE_ROW  = 'FFFFF3CD';
    const COLOR_TIME_COL    = 'FFECF0F1';
    const COLOR_HEADER_TEXT = 'FFFFFFFF';
    const COLOR_ORANGE_TEXT = 'FF7A5C00';
    const COLOR_RED_BG      = 'FFFF4444';
    const COLOR_RED_TEXT    = 'FFFFFFFF';

    // Time is first in every app section
    const SUB_COLS = ['Time', '51la IP', 'Install', 'Click', 'Click Ratio', 'IP Click Ratio', 'Conv. Rate'];
    const CONV_RATE_IDX = 6; // 0-based index of Conv. Rate inside SUB_COLS
    const TIME_IDX      = 0; // 0-based index of Time inside SUB_COLS

    public function __construct(string $date)
    {
        $this->date = $date;
        $this->apps = App::where('is_active', true)->orderBy('id')->get();
    }

    public function title(): string
    {
        return 'Report ' . $this->date;
    }

    public function array(): array
    {
        $allSlots = [];
        for ($h = 0; $h < 24; $h++) {
            $allSlots[] = sprintf('%02d:00', $h);
        }

        $metricsRaw = AppMetric::where('report_date', $this->date)
            ->get()
            ->groupBy('app_id')
            ->map(fn($g) => $g->keyBy('time_slot'));

        $subCount = count(self::SUB_COLS);

        // ── Header row 1: App names (colspan = subCount each) ─
        $row1 = [];
        foreach ($this->apps as $app) {
            $row1[] = $app->name;
            for ($i = 1; $i < $subCount; $i++) $row1[] = '';
        }

        // ── Header row 2: Sub-columns per app ─────────────────
        $row2 = [];
        foreach ($this->apps as $_) {
            foreach (self::SUB_COLS as $c) $row2[] = $c;
        }

        $data     = [$row1, $row2];
        $excelRow = 3;

        foreach ($allSlots as $slot) {
            $cumRow = [];
            $intRow = [];

            foreach ($this->apps as $appIdx => $app) {
                $m = $metricsRaw[$app->id][$slot] ?? null;

                // Excel column index (1-based) for this app's Conv. Rate
                $convCol = ($appIdx * $subCount) + self::CONV_RATE_IDX + 1;

                if ($m) {
                    // Cumulative row — Time first, then metrics
                    $cumRow[] = $slot;
                    $cumRow[] = $m->ip_51la;
                    $cumRow[] = $m->total_install;
                    $cumRow[] = $m->total_click;
                    $cumRow[] = $m->click_ratio ?? '-';
                    $cumRow[] = $m->ip_click_ratio ?? '-';
                    $cumConv  = $m->conversion_rate !== null ? (float)($m->conversion_rate * 100) : null;
                    $cumRow[] = $cumConv !== null ? number_format($cumConv, 2) . '%' : '-';
                    $this->convRateCells[] = ['row' => $excelRow, 'col' => $convCol, 'value' => $cumConv];

                    // Interval row — 小时段 first, then metrics
                    $intRow[] = '小时段';
                    $intRow[] = $m->interval_ip;
                    $intRow[] = $m->interval_install;
                    $intRow[] = $m->interval_click;
                    $intRow[] = $m->interval_click_ratio ?? '-';
                    $intRow[] = $m->interval_ip_click_ratio ?? '-';
                    $intConv  = $m->interval_conversion_rate !== null ? (float)($m->interval_conversion_rate * 100) : null;
                    $intRow[] = $intConv !== null ? number_format($intConv, 2) . '%' : '-';
                    $this->convRateCells[] = ['row' => $excelRow + 1, 'col' => $convCol, 'value' => $intConv];

                } else {
                    $cumRow[] = $slot;
                    $intRow[] = '小时段';
                    for ($i = 1; $i < $subCount; $i++) { $cumRow[] = '-'; $intRow[] = '-'; }
                    $this->convRateCells[] = ['row' => $excelRow,     'col' => $convCol, 'value' => null];
                    $this->convRateCells[] = ['row' => $excelRow + 1, 'col' => $convCol, 'value' => null];
                }
            }

            $data[] = $cumRow;
            $data[] = $intRow;
            $excelRow += 2;
        }

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $appCount  = $this->apps->count();
                $subCount  = count(self::SUB_COLS);
                $totalCols = $appCount * $subCount;
                $totalRows = 2 + (24 * 2);

                $col = fn(int $n) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);

                // ── Merge app name cells in row 1 ────────────
                for ($a = 0; $a < $appCount; $a++) {
                    $s = 1 + ($a * $subCount);
                    $e = $s + $subCount - 1;
                    $sheet->mergeCells($col($s) . '1:' . $col($e) . '1');
                }

                // ── Header row 1: App names ───────────────────
                $sheet->getStyle('A1:' . $col($totalCols) . '1')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_HEADER_TEXT], 'size' => 11, 'name' => 'Arial'],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER_DARK]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // ── Header row 2: Sub-columns ─────────────────
                $sheet->getStyle('A2:' . $col($totalCols) . '2')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_HEADER_TEXT], 'size' => 10, 'name' => 'Arial'],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER_MID]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // ── Data rows ────────────────────────────────
                for ($i = 0; $i < 24; $i++) {
                    $wRow = 3 + ($i * 2);
                    $oRow = $wRow + 1;

                    // White row
                    $sheet->getStyle('A' . $wRow . ':' . $col($totalCols) . $wRow)->applyFromArray([
                        'font'      => ['bold' => true, 'name' => 'Arial', 'size' => 10],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_WHITE_ROW]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);

                    // Orange row
                    $sheet->getStyle('A' . $oRow . ':' . $col($totalCols) . $oRow)->applyFromArray([
                        'font'      => ['bold' => false, 'color' => ['argb' => self::COLOR_ORANGE_TEXT], 'name' => 'Arial', 'size' => 10],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_ORANGE_ROW]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);

                    // Time columns (first col of each app section) — special styling
                    for ($a = 0; $a < $appCount; $a++) {
                        $timeColIdx = 1 + ($a * $subCount); // 1-based
                        foreach ([$wRow, $oRow] as $r) {
                            $sheet->getStyle($col($timeColIdx) . $r)->applyFromArray([
                                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_TIME_COL]],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                                'font'      => ['bold' => true, 'color' => ['argb' => 'FF2C3E50'], 'name' => 'Arial', 'size' => 10],
                            ]);
                        }
                    }
                }

                // ── Conv. Rate: red if < 30% ─────────────────
                foreach ($this->convRateCells as $cell) {
                    if ($cell['value'] !== null && $cell['value'] < 30) {
                        $addr = $col($cell['col']) . $cell['row'];
                        $sheet->getStyle($addr)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_RED_BG]],
                            'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_RED_TEXT], 'name' => 'Arial', 'size' => 10],
                        ]);
                    }
                }

                // ── Borders ───────────────────────────────────
                $sheet->getStyle('A1:' . $col($totalCols) . $totalRows)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
                ]);

                // ── Column widths ────────────────────────────
                for ($a = 0; $a < $appCount; $a++) {
                    $timeColIdx = 1 + ($a * $subCount);
                    $sheet->getColumnDimension($col($timeColIdx))->setWidth(10); // Time col
                    for ($s = 1; $s < $subCount; $s++) {
                        $sheet->getColumnDimension($col($timeColIdx + $s))->setWidth(13);
                    }
                }

                // ── Row heights ───────────────────────────────
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(18);
                for ($r = 3; $r <= $totalRows; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(16);
                }

                // No freeze — all app sections scroll freely
            },
        ];
    }
}