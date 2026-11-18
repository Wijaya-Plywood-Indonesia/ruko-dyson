<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export rekap penjualan: 2 sheet.
 *  1. "Ringkasan"   -> angka ringkas, per metode, per status, barang terlaris
 *  2. "Daftar Nota" -> satu baris per nota
 *
 * Datanya diambil dari RekapPenjualan::rekap() supaya angka di layar = angka di Excel.
 */
class RekapPenjualanExport implements WithMultipleSheets
{
    // Format Rupiah yang sama dengan export lain di proyek ini.
    public const RUPIAH = '_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)';

    public function __construct(
        protected array $rekap,
        protected string $periode,
        protected string $statusLabel,
    ) {}

    public function sheets(): array
    {
        return [
            $this->sheetRingkasan(),
            $this->sheetDaftarNota(),
        ];
    }

    /* ================= SHEET 1: RINGKASAN ================= */
    protected function sheetRingkasan()
    {
        return new class($this->rekap, $this->periode, $this->statusLabel) implements
            FromArray,
            WithTitle,
            WithStyles,
            WithColumnWidths
        {
            protected array $rows = [];
            protected array $gaya = [];
            protected array $uang = [];

            public function __construct(array $rekap, string $periode, string $statusLabel)
            {
                $this->tambah(['REKAP PENJUALAN'], 'judul');
                $this->tambah(['Periode', $periode]);
                $this->tambah(['Status', $statusLabel]);
                $this->tambah([null]);

                $this->tambah(['Ringkasan'], 'seksi');
                $this->tambah(['Jumlah Nota', $rekap['jumlah']]);
                $this->tambah(['Item Terjual', $rekap['item']]);
                $this->tambah(['Total Omzet', $rekap['omzet']], '', ['B']);
                $this->tambah(['Total Diskon', $rekap['diskon']], '', ['B']);
                $this->tambah(['Rata-rata per Nota', $rekap['rata']], '', ['B']);
                $this->tambah([null]);

                $this->tambah(['Per Metode Pembayaran'], 'seksi');
                $this->tambah(['Metode', 'Jumlah Nota', 'Total'], 'head');
                foreach ($rekap['perMetode'] as $b) {
                    $this->tambah([$b['nama'], $b['jumlah'], $b['nilai']], '', ['C']);
                }
                $this->tambah([null]);

                $this->tambah(['Per Status'], 'seksi');
                $this->tambah(['Status', 'Jumlah Nota', 'Total'], 'head');
                foreach ($rekap['perStatus'] as $b) {
                    $this->tambah([$b['nama'], $b['jumlah'], $b['nilai']], '', ['C']);
                }
                $this->tambah([null]);

                $this->tambah(['10 Barang Terlaris'], 'seksi');
                $this->tambah(['Nama Barang', 'Qty Terjual', 'Total Penjualan'], 'head');
                foreach ($rekap['topBarang'] as $b) {
                    $this->tambah([$b['nama'], $b['qty'], $b['nilai']], '', ['C']);
                }
            }

            /** Tambah satu baris + catat gaya dan kolom uang untuk baris itu. */
            protected function tambah(array $baris, string $gaya = '', array $kolomUang = []): void
            {
                $this->rows[] = $baris;
                $n = count($this->rows);

                if ($gaya !== '') {
                    $this->gaya[$n] = $gaya;
                }
                foreach ($kolomUang as $kolom) {
                    $this->uang[] = $kolom . $n;
                }
            }

            public function array(): array
            {
                return $this->rows;
            }

            public function styles(Worksheet $sheet)
            {
                foreach ($this->gaya as $n => $gaya) {
                    if ($gaya === 'judul') {
                        $sheet->getStyle("A{$n}")->getFont()->setBold(true)->setSize(14);
                    } elseif ($gaya === 'seksi') {
                        $sheet->getStyle("A{$n}:C{$n}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'E5E7EB'],
                            ],
                        ]);
                    } elseif ($gaya === 'head') {
                        $sheet->getStyle("A{$n}:C{$n}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '1F4ED8'],
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                    }
                }

                foreach ($this->uang as $sel) {
                    $sheet->getStyle($sel)->getNumberFormat()->setFormatCode(RekapPenjualanExport::RUPIAH);
                }

                // Kolom B (angka/periode) rata kiri supaya rapi di blok ringkasan.
                $sheet->getStyle('B1:B' . count($this->rows))
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                return [];
            }

            public function columnWidths(): array
            {
                return ['A' => 38, 'B' => 24, 'C' => 20];
            }

            public function title(): string
            {
                return 'Ringkasan';
            }
        };
    }

    /* ================= SHEET 2: DAFTAR NOTA ================= */
    protected function sheetDaftarNota()
    {
        return new class($this->rekap['notas']) implements
            FromArray,
            WithHeadings,
            WithTitle,
            WithStyles,
            WithColumnWidths
        {
            public function __construct(protected array $notas) {}

            public function array(): array
            {
                return array_map(fn ($n) => [
                    $n['no_nota'],
                    $n['tanggal'],
                    $n['customer'],
                    $n['metode'],
                    $n['status'],
                    $n['kasir'],
                    (float) $n['total'],
                ], $this->notas);
            }

            public function headings(): array
            {
                return ['No Nota', 'Tanggal', 'Customer', 'Metode Bayar', 'Status', 'Kasir', 'Total'];
            }

            public function styles(Worksheet $sheet)
            {
                $last = count($this->notas) + 1;

                $sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4ED8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                if ($last >= 2) {
                    $sheet->getStyle("A2:G{$last}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                    $sheet->getStyle("G2:G{$last}")->getNumberFormat()
                        ->setFormatCode(RekapPenjualanExport::RUPIAH);
                }

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:G{$last}");

                return [];
            }

            public function columnWidths(): array
            {
                return ['A' => 24, 'B' => 18, 'C' => 28, 'D' => 16, 'E' => 16, 'F' => 22, 'G' => 20];
            }

            public function title(): string
            {
                return 'Daftar Nota';
            }
        };
    }
}