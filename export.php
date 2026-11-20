<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';

$type = $_GET['type'] ?? 'keuangan';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if ($type === 'keuangan') {

    require_once __DIR__ . '/vendor/autoload.php';

    // Ambil semua transaksi valid beserta barang
    $rows = $pdo->query("
        SELECT t.tanggal, t.deskripsi, t.tipe, t.jumlah, t.kategori, t.status,
               b.nama AS nama_barang, b.satuan, t.jumlah_unit, t.barang_id
        FROM transaksi t
        LEFT JOIN barang b ON b.id = t.barang_id
        WHERE t.status = 'valid'
        ORDER BY t.tanggal ASC, t.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Group per tanggal
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['tanggal']][] = $row;
    }

    // Buat spreadsheet
    $spreadsheet = new Spreadsheet();
    $ws = $spreadsheet->getActiveSheet();
    $ws->setTitle('Laporan Keuangan');

    // Column widths
    $ws->getColumnDimension('A')->setWidth(6);
    $ws->getColumnDimension('B')->setWidth(12);
    $ws->getColumnDimension('C')->setWidth(55);
    $ws->getColumnDimension('D')->setWidth(20);
    $ws->getColumnDimension('E')->setWidth(15);

    $navy  = '1F3864';
    $white = 'FFFFFF';
    $gray  = 'D9D9D9';

    // Style helpers
    $styleTitleBase = [
        'font'      => ['bold' => true, 'color' => ['rgb' => $white], 'name' => 'Arial', 'size' => 11],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ];
    $styleHeaderCol = [
        'font'      => ['bold' => true, 'color' => ['rgb' => $white], 'name' => 'Arial', 'size' => 10],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ];
    $styleTotalRow = [
        'font'      => ['bold' => true, 'name' => 'Arial', 'size' => 10],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $gray]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ];
    $borderOnly = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ];

    // Ambil nama bulan dari data pertama untuk judul
    $firstDate = array_key_first($grouped) ?? date('Y-m-d');
    $bulanTahun = strtoupper(date('F Y', strtotime($firstDate)));
    // Map bulan ke Indonesia
    $bulanId = [
        'JANUARY' => 'JANUARI',
        'FEBRUARY' => 'FEBRUARI',
        'MARCH' => 'MARET',
        'APRIL' => 'APRIL',
        'MAY' => 'MEI',
        'JUNE' => 'JUNI',
        'JULY' => 'JULI',
        'AUGUST' => 'AGUSTUS',
        'SEPTEMBER' => 'SEPTEMBER',
        'OCTOBER' => 'OKTOBER',
        'NOVEMBER' => 'NOVEMBER',
        'DECEMBER' => 'DESEMBER'
    ];
    foreach ($bulanId as $en => $id) {
        $bulanTahun = str_replace($en, $id, $bulanTahun);
    }

    // === ROW 1: spacer ===
    $ws->getRowDimension(1)->setRowHeight(8);

    // === ROW 2: Judul ===
    $ws->getRowDimension(2)->setRowHeight(30);
    $ws->mergeCells('A2:E2');
    $ws->setCellValue('A2', 'LAPORAN KEUANGAN TATA URUSAN KOPERASI DAN KEWIRAUSAHAAN SEKOLAH TINGGI INTELIJEN NEGARA');
    $ws->getStyle('A2')->applyFromArray($styleTitleBase);

    // === ROW 3: Bulan ===
    $ws->getRowDimension(3)->setRowHeight(22);
    $ws->mergeCells('A3:E3');
    $ws->setCellValue('A3', 'BULAN ' . $bulanTahun);
    $ws->getStyle('A3')->applyFromArray($styleTitleBase);

    // === ROW 4: Header kolom ===
    $ws->getRowDimension(4)->setRowHeight(22);
    foreach (['A4' => 'No', 'B4' => 'Tanggal', 'C4' => 'Uraian', 'D4' => 'Debet', 'E4' => 'Kredit'] as $cell => $val) {
        $ws->setCellValue($cell, $val);
        $ws->getStyle($cell)->applyFromArray($styleHeaderCol);
    }

    $cur = 5;
    $no  = 1;
    $grandDebet  = 0;
    $grandKredit = 0;

    // Helper: tulis baris header grup (pemasukan/pengeluaran)
    $writeGroupHeader = function (int $row, $no, string $tgl, string $label, float $total, string $kolom) use ($ws, &$Alignment, &$Border) {
        $ws->getRowDimension($row)->setRowHeight(18);
        $styleBase = ['name' => 'Arial', 'size' => 10];
        $borderAll = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]];

        if ($no !== null) {
            $ws->setCellValue("A{$row}", $no);
            $ws->getStyle("A{$row}")->applyFromArray(['font' => array_merge($styleBase, ['bold' => true]), 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => $borderAll]);
        } else {
            $ws->getStyle("A{$row}")->applyFromArray(['borders' => $borderAll]);
        }

        if ($tgl !== '') {
            $ws->setCellValue("B{$row}", $tgl);
            $ws->getStyle("B{$row}")->applyFromArray(['font' => array_merge($styleBase, ['bold' => true]), 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => $borderAll]);
        } else {
            $ws->getStyle("B{$row}")->applyFromArray(['borders' => $borderAll]);
        }

        $ws->setCellValue("C{$row}", $label);
        $ws->getStyle("C{$row}")->applyFromArray(['font' => array_merge($styleBase, ['bold' => true]), 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1], 'borders' => $borderAll]);

        // Tulis total di kolom D (debet) atau E (kredit)
        $lain = $kolom === 'D' ? 'E' : 'D';
        $ws->setCellValue("{$kolom}{$row}", $total);
        $ws->getStyle("{$kolom}{$row}")->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
        $ws->getStyle("{$kolom}{$row}")->applyFromArray(['font' => array_merge($styleBase, ['bold' => true]), 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => $borderAll]);
        $ws->setCellValue("{$lain}{$row}", '-');
        $ws->getStyle("{$lain}{$row}")->applyFromArray(['font' => $styleBase, 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => $borderAll]);
    };

    // Helper: tulis baris item
    $writeItem = function (int $row, string $uraian) use ($ws, &$Border) {
        $ws->getRowDimension($row)->setRowHeight(16);
        $borderAll = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]];
        foreach (['A', 'B', 'D', 'E'] as $col) {
            $ws->getStyle("{$col}{$row}")->applyFromArray(['borders' => $borderAll]);
        }
        $ws->setCellValue("C{$row}", $uraian);
        $ws->getStyle("C{$row}")->applyFromArray(['font' => ['name' => 'Arial', 'size' => 10], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 3], 'borders' => $borderAll]);
    };

    // Helper: format uraian item
    $formatUraian = function (array $t): string {
        $hargaFmt = 'Rp ' . number_format((float)$t['jumlah'], 0, ',', '.');
        if ($t['nama_barang']) {
            $unit   = $t['jumlah_unit'] ? $t['jumlah_unit'] . ' ' : '';
            $satuan = $t['satuan'] ?? 'pcs';
            return $t['nama_barang'] . ' ' . $unit . $satuan . ' = ' . $hargaFmt;
        }
        return ($t['deskripsi'] ?? 'Item') . ' = ' . $hargaFmt;
    };

    foreach ($grouped as $tgl => $transaksiList) {
        $tglFormatted = date('j-M', strtotime($tgl));

        // Pisah pemasukan & pengeluaran
        $pemasukan   = array_filter($transaksiList, fn($t) => $t['tipe'] === 'pemasukan');
        $pengeluaran = array_filter($transaksiList, fn($t) => $t['tipe'] === 'pengeluaran');

        $totalDebet  = array_sum(array_column(array_values($pemasukan),  'jumlah'));
        $totalKredit = array_sum(array_column(array_values($pengeluaran), 'jumlah'));

        $isFirst = true; // nomor & tanggal hanya di baris pertama per hari

        // === PEMASUKAN ===
        if (!empty($pemasukan)) {
            // Pisah berdasarkan kategori: penjualan vs lainnya
            $penjualan = array_filter($pemasukan, fn($t) => ($t['kategori'] ?? '') === 'penjualan');
            $pemasukanLain = array_filter($pemasukan, fn($t) => ($t['kategori'] ?? '') !== 'penjualan');

            // Grup penjualan → label "Hasil Penjualan Kebutuhan Taruna"
            if (!empty($penjualan)) {
                $totalPenjualan = array_sum(array_column(array_values($penjualan), 'jumlah'));
                $writeGroupHeader($cur, $no, $tglFormatted, 'Hasil Penjualan Kebutuhan Taruna', $totalPenjualan, 'D');
                $cur++;
                foreach ($penjualan as $t) {
                    $writeItem($cur, $formatUraian($t));
                    $cur++;
                }
                $isFirst = false;
            }

            // Grup pemasukan lain → label sesuai kategori masing-masing
            foreach ($pemasukanLain as $t) {
                $labelKat = match($t['kategori'] ?? '') {
                    'operasional' => 'Pemasukan Operasional',
                    'gaji'        => 'Pemasukan Gaji',
                    'lainnya'     => 'Pemasukan Lainnya',
                    default       => 'Pemasukan ' . ucfirst($t['kategori'] ?? 'Lainnya'),
                };
                $noKol  = $isFirst ? $no : null;
                $tglKol = $isFirst ? $tglFormatted : '';
                $writeGroupHeader($cur, $noKol, $tglKol, $labelKat, (float)$t['jumlah'], 'D');
                $cur++;
                $writeItem($cur, $formatUraian($t));
                $cur++;
                $isFirst = false;
            }

            $grandDebet += $totalDebet;
        }

        // === PENGELUARAN ===
        if (!empty($pengeluaran)) {
            // Jika sudah ada pemasukan di hari sama, no & tanggal kosong
            $noKol  = $isFirst ? $no        : null;
            $tglKol = $isFirst ? $tglFormatted : '';
            $writeGroupHeader($cur, $noKol, $tglKol, 'Pengeluaran Kebutuhan Taruna', $totalKredit, 'E');
            $cur++;
            foreach ($pengeluaran as $t) {
                $writeItem($cur, $formatUraian($t));
                $cur++;
            }
            $grandKredit += $totalKredit;
        }

        $no++;
    }

    $saldo = $grandDebet - $grandKredit;

    // === Baris TOTAL ===
    $ws->getRowDimension($cur)->setRowHeight(20);
    $ws->mergeCells("A{$cur}:C{$cur}");
    $ws->setCellValue("A{$cur}", 'TOTAL');
    $ws->getStyle("A{$cur}:E{$cur}")->applyFromArray($styleTotalRow);
    $ws->setCellValue("D{$cur}", $grandDebet);
    $ws->getStyle("D{$cur}")->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
    $ws->getStyle("D{$cur}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $ws->setCellValue("E{$cur}", $grandKredit);
    $ws->getStyle("E{$cur}")->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
    $ws->getStyle("E{$cur}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $cur++;

    // === Baris Keuntungan / Saldo ===
    $ws->getRowDimension($cur)->setRowHeight(20);
    $ws->getStyle("A{$cur}:E{$cur}")->applyFromArray($borderOnly);
    $labelSaldo = $saldo >= 0 ? 'Keuntungan' : 'Kerugian';
    $ws->setCellValue("C{$cur}", $labelSaldo . ' Bulan ' . ucwords(strtolower($bulanTahun)));
    $ws->getStyle("C{$cur}")->applyFromArray([
        'font'      => ['bold' => true, 'name' => 'Arial', 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ]);
    $ws->setCellValue("D{$cur}", abs($saldo));
    $ws->getStyle("D{$cur}")->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
    $ws->getStyle("D{$cur}")->applyFromArray([
        'font'      => ['bold' => true, 'name' => 'Arial', 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ]);

    // Output
    $filename = 'laporan_keuangan_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} elseif ($type === 'saran') {

    $rows = $pdo->query("SELECT created_at, nama, nis_nip, kategori, isi_saran, status FROM saran ORDER BY created_at DESC")->fetchAll();
    $filename = 'laporan_saran_' . date('Y-m-d') . '.csv';
    $headers  = ['Tanggal', 'Nama', 'NIS/NIP', 'Kategori', 'Isi Saran', 'Status'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
} elseif ($type === 'kebutuhan') {

    $rows = $pdo->query("SELECT created_at, nama, angkatan, jenis, item, jumlah, tanggal_dibutuhkan, status FROM kebutuhan ORDER BY created_at DESC")->fetchAll();
    $filename = 'laporan_kebutuhan_' . date('Y-m-d') . '.csv';
    $headers  = ['Tanggal', 'Nama', 'Angkatan', 'Jenis', 'Item', 'Jumlah', 'Tgl Dibutuhkan', 'Status'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
} else {
    die('Tipe tidak valid.');
}
