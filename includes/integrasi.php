<?php
/**
 * includes/integrasi.php
 * ─────────────────────────────────────────────────────────────────
 * Logika integrasi antara Transaksi (keuangan) dan Stok (barang).
 *
 * ATURAN BISNIS:
 *  - Transaksi PENJUALAN   (pemasukan  + barang dipilih) → stok KELUAR otomatis
 *  - Transaksi PEMBELIAN   (pengeluaran+ barang dipilih) → stok MASUK  otomatis
 *  - Hapus transaksi yang punya barang                  → stok di-rollback
 *  - Catat stok masuk manual + centang "buat transaksi" → pengeluaran otomatis
 *  - Catat stok keluar manual + centang "buat transaksi"→ pemasukan otomatis
 * ─────────────────────────────────────────────────────────────────
 */

/**
 * Catat gerakan stok DAN update kolom stok_saat_ini di tabel barang.
 * Return: stok sesudah
 */
function catatGerakan(PDO $pdo, int $barangId, string $tipe, int $jumlah,
                      string $tanggal, string $keterangan = '',
                      ?string $referensi = null, ?int $transaksiId = null,
                      ?int $userId = null): int
{
    // Ambil stok sekarang (lock row)
    $s = $pdo->prepare("SELECT stok_saat_ini FROM barang WHERE id = ? FOR UPDATE");
    $s->execute([$barangId]);
    $cur = (int)($s->fetchColumn() ?? 0);

    $sesudah = ($tipe === 'masuk') ? $cur + $jumlah : max(0, $cur - $jumlah);

    $pdo->prepare("
        INSERT INTO stok_gerakan
            (barang_id, tipe, jumlah, stok_sebelum, stok_sesudah,
             tanggal, keterangan, referensi, transaksi_id, user_id)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ")->execute([$barangId, $tipe, $jumlah, $cur, $sesudah,
                 $tanggal, $keterangan ?: null, $referensi, $transaksiId, $userId]);

    $pdo->prepare("UPDATE barang SET stok_saat_ini = ? WHERE id = ?")
        ->execute([$sesudah, $barangId]);

    return $sesudah;
}

/**
 * Buat transaksi keuangan dan (jika ada barang) catat gerakan stok otomatis.
 * Return: ['transaksi_id' => int, 'stok_sesudah' => int|null]
 */
function buatTransaksi(PDO $pdo, array $data, ?int $userId = null): array
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO transaksi
                (tanggal, deskripsi, tipe, jumlah, kategori,
                 barang_id, jumlah_unit, status, user_id)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $data['tanggal'],
            $data['deskripsi'],
            $data['tipe'],
            $data['jumlah'],
            $data['kategori'] ?: null,
            $data['barang_id'] ?: null,
            $data['jumlah_unit'] ?: null,
            $data['status'] ?? 'pending',
            $userId,
        ]);
        $trxId = (int)$pdo->lastInsertId();

        $stokSesudah = null;
        // Integrasi stok: hanya jika barang dipilih dan ada jumlah unit
        if (!empty($data['barang_id']) && !empty($data['jumlah_unit']) && (int)$data['jumlah_unit'] > 0) {
            $tipGerak = ($data['tipe'] === 'pemasukan') ? 'keluar' : 'masuk';
            // Penjualan → stok keluar | Pembelian bahan → stok masuk
            $stokSesudah = catatGerakan(
                $pdo,
                (int)$data['barang_id'],
                $tipGerak,
                (int)$data['jumlah_unit'],
                $data['tanggal'],
                $data['deskripsi'],
                'TRX-' . $trxId,
                $trxId,
                $userId
            );
        }

        $pdo->commit();
        return ['transaksi_id' => $trxId, 'stok_sesudah' => $stokSesudah];

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Hapus transaksi DAN rollback gerakan stok terkait.
 */
function hapusTransaksi(PDO $pdo, int $trxId): void
{
    $pdo->beginTransaction();
    try {
        // Cari gerakan stok yang terhubung
        $gs = $pdo->prepare("SELECT * FROM stok_gerakan WHERE transaksi_id = ?");
        $gs->execute([$trxId]);
        $gerakans = $gs->fetchAll();

        foreach ($gerakans as $g) {
            // Balikkan gerakan: masuk→kurangi, keluar→tambah
            $reverse = ($g['tipe'] === 'masuk') ? 'keluar' : 'masuk';
            catatGerakan(
                $pdo,
                $g['barang_id'],
                $reverse,
                $g['jumlah'],
                date('Y-m-d'),
                'Rollback hapus transaksi #' . $trxId,
                null,
                null
            );
            $pdo->prepare("DELETE FROM stok_gerakan WHERE id = ?")->execute([$g['id']]);
        }

        $pdo->prepare("DELETE FROM transaksi WHERE id = ?")->execute([$trxId]);
        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Recalculate stok barang dari semua gerakan (untuk konsistensi data).
 */
function recalcStok(PDO $pdo, int $barangId): void
{
    $r = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE -jumlah END), 0)
        FROM stok_gerakan WHERE barang_id = ?
    ");
    $r->execute([$barangId]);
    $stok = max(0, (int)$r->fetchColumn());
    $pdo->prepare("UPDATE barang SET stok_saat_ini = ? WHERE id = ?")->execute([$stok, $barangId]);
}
