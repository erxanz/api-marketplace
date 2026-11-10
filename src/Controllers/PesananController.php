<?php

namespace App\Controllers;

use App\Database;
use App\Request;
use App\Response;

class PesananController
{
    public function index(array $params, ?array $user): void
    {
        $pdo = Database::get();
        $role = $user['role'];

        $where = '1=1';
        $bind = [];

        if ($role !== 'admin') {
            if ($role === 'penjual') {
                // Penjual melihat pesanan yang memuat produknya
                $where = 'EXISTS (
                    SELECT 1 FROM detail_pesanan dp
                    JOIN produk p ON p.id_produk = dp.id_produk
                    WHERE dp.id_pesanan = pesanan.id_pesanan AND p.id_penjual = ?
                )';
                $bind[] = (int) $user['id_user'];
            } else {
                $where = 'pesanan.id_user = ?';
                $bind[] = (int) $user['id_user'];
            }
        }

        $status = Request::query('status');
        if ($status !== null && $status !== '') {
            $where .= ' AND pesanan.status = ?';
            $bind[] = $status;
        }

        $stmt = $pdo->prepare(
            "SELECT pesanan.*,
                    u.nama AS nama_pembeli,
                    (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan = pesanan.id_pesanan) AS jumlah_produk
             FROM pesanan
             JOIN users u ON u.id_user = pesanan.id_user
             WHERE $where
             ORDER BY pesanan.tanggal_pesanan DESC, pesanan.id_pesanan DESC"
        );
        $stmt->execute($bind);

        Response::success($stmt->fetchAll(), 'Daftar pesanan');
    }

    public function show(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        $pdo = Database::get();

        $stmt = $pdo->prepare(
            'SELECT pesanan.*, u.nama AS nama_pembeli, u.email AS email_pembeli, u.no_hp AS no_hp_pembeli
             FROM pesanan
             JOIN users u ON u.id_user = pesanan.id_user
             WHERE pesanan.id_pesanan = ?'
        );
        $stmt->execute([$id]);
        $pesanan = $stmt->fetch();

        if (!$pesanan) Response::notFound('Pesanan tidak ditemukan');

        $this->authorizeView($pesanan, $user);

        $stmt = $pdo->prepare(
            'SELECT dp.*,
                    p.nama_produk, p.gambar,
                    k.nama_kategori,
                    u.nama AS nama_penjual
             FROM detail_pesanan dp
             JOIN produk p ON p.id_produk = dp.id_produk
             JOIN kategori k ON k.id_kategori = p.id_kategori
             JOIN users u ON u.id_user = p.id_penjual
             WHERE dp.id_pesanan = ?
             ORDER BY dp.id_detail_pesanan'
        );
        $stmt->execute([$id]);
        $pesanan['items'] = $stmt->fetchAll();

        Response::success($pesanan, 'Detail pesanan');
    }

    public function checkout(array $params, ?array $user): void
    {
        if ($user['role'] !== 'pembeli') {
            Response::error('Hanya pembeli yang bisa checkout', 403);
        }

        $alamat = trim((string) Request::input('alamat_pengiriman', $user['alamat'] ?? ''));
        $metode = (string) Request::input('metode_pembayaran', 'transfer');

        $errors = [];
        if ($alamat === '') $errors['alamat_pengiriman'] = 'alamat pengiriman wajib diisi';
        if (!in_array($metode, ['transfer', 'ewallet', 'cod'], true)) {
            $errors['metode_pembayaran'] = 'metode harus transfer/ewallet/cod';
        }
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $pdo = Database::get();
        $pdo->beginTransaction();

        try {
            $idUserId = (int) $user['id_user'];

            $stmt = $pdo->prepare('SELECT id_keranjang FROM keranjang WHERE id_user = ? AND status = "aktif" ORDER BY id_keranjang DESC LIMIT 1');
            $stmt->execute([$idUserId]);
            $cart = $stmt->fetch();

            if (!$cart) {
                $pdo->rollBack();
                Response::error('Keranjang kosong', 409);
            }
            $idKeranjang = (int) $cart['id_keranjang'];

            $stmt = $pdo->prepare(
                'SELECT dk.id_produk, dk.jumlah, p.harga, p.stok, p.status, p.nama_produk
                 FROM detail_keranjang dk
                 JOIN produk p ON p.id_produk = dk.id_produk
                 WHERE dk.id_keranjang = ?'
            );
            $stmt->execute([$idKeranjang]);
            $items = $stmt->fetchAll();

            if (!$items) {
                $pdo->rollBack();
                Response::error('Keranjang kosong', 409);
            }

            $total = 0;
            foreach ($items as $item) {
                if ($item['status'] !== 'aktif') {
                    $pdo->rollBack();
                    Response::error("Produk \"{$item['nama_produk']}\" tidak tersedia", 409);
                }
                if ((int) $item['jumlah'] > (int) $item['stok']) {
                    $pdo->rollBack();
                    Response::error("Stok \"{$item['nama_produk']}\" tidak mencukupi", 409, [
                        'stok_tersedia' => (int) $item['stok'],
                    ]);
                }
                $total += (float) $item['harga'] * (int) $item['jumlah'];
            }

            // Buat pesanan
            $stmt = $pdo->prepare(
                'INSERT INTO pesanan (id_user, alamat_pengiriman, total_harga, metode_pembayaran, status)
                 VALUES (?, ?, ?, ?, "menunggu")'
            );
            $stmt->execute([$idUserId, $alamat, $total, $metode]);
            $idPesanan = (int) $pdo->lastInsertId();

            // Detail + kurangi stok
            $insertDetail = $pdo->prepare(
                'INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_saat_pesan, subtotal)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $updateStok = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id_produk = ?');

            foreach ($items as $item) {
                $jumlah = (int) $item['jumlah'];
                $subtotal = (float) $item['harga'] * $jumlah;
                $insertDetail->execute([$idPesanan, $item['id_produk'], $jumlah, $item['harga'], $subtotal]);
                $updateStok->execute([$jumlah, $item['id_produk']]);
            }

            // Tandai keranjang checkout + kosongkan detail
            $pdo->prepare('UPDATE keranjang SET status = "checkout" WHERE id_keranjang = ?')->execute([$idKeranjang]);
            $pdo->prepare('DELETE FROM detail_keranjang WHERE id_keranjang = ?')->execute([$idKeranjang]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('Checkout gagal: ' . $e->getMessage(), 500);
        }

        Response::success($this->find($idPesanan, $user), 'Checkout berhasil', 201);
    }

    public function updateStatus(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        $status = (string) Request::input('status', '');

        $allowed = ['menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];
        if (!in_array($status, $allowed, true)) {
            Response::error('Status tidak valid', 422, ['status' => 'harus salah satu dari: ' . implode(', ', $allowed)]);
        }

        $pesanan = $this->findRaw($id);
        if (!$pesanan) Response::notFound('Pesanan tidak ditemukan');

        $this->authorizeManage($pesanan, $user, $status);

        $pdo = Database::get();

        // Jika dibatalkan, kembalikan stok
        if ($status === 'dibatalkan' && $pesanan['status'] !== 'dibatalkan') {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT id_produk, jumlah FROM detail_pesanan WHERE id_pesanan = ?');
                $stmt->execute([$id]);
                $restore = $pdo->prepare('UPDATE produk SET stok = stok + ? WHERE id_produk = ?');
                foreach ($stmt->fetchAll() as $row) {
                    $restore->execute([(int) $row['jumlah'], (int) $row['id_produk']]);
                }
                $pdo->prepare('UPDATE pesanan SET status = ? WHERE id_pesanan = ?')->execute([$status, $id]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                Response::error('Gagal memperbarui status', 500);
            }
        } else {
            $pdo->prepare('UPDATE pesanan SET status = ? WHERE id_pesanan = ?')->execute([$status, $id]);
        }

        Response::success($this->find($id, $user), 'Status pesanan diperbarui');
    }

    private function findRaw(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM pesanan WHERE id_pesanan = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function find(int $id, array $user): array
    {
        $pesanan = $this->findRaw($id);
        $this->authorizeView($pesanan, $user);

        $stmt = Database::get()->prepare(
            'SELECT dp.*, p.nama_produk, p.gambar, u.nama AS nama_penjual
             FROM detail_pesanan dp
             JOIN produk p ON p.id_produk = dp.id_produk
             JOIN users u ON u.id_user = p.id_penjual
             WHERE dp.id_pesanan = ?
             ORDER BY dp.id_detail_pesanan'
        );
        $stmt->execute([$id]);
        $pesanan['items'] = $stmt->fetchAll();
        return $pesanan;
    }

    private function authorizeView(?array $pesanan, array $user): void
    {
        if (!$pesanan) Response::notFound('Pesanan tidak ditemukan');
        if ($user['role'] === 'admin') return;
        if ((int) $pesanan['id_user'] === (int) $user['id_user']) return;

        if ($user['role'] === 'penjual') {
            $stmt = Database::get()->prepare(
                'SELECT COUNT(*) FROM detail_pesanan dp
                 JOIN produk p ON p.id_produk = dp.id_produk
                 WHERE dp.id_pesanan = ? AND p.id_penjual = ?'
            );
            $stmt->execute([(int) $pesanan['id_pesanan'], (int) $user['id_user']]);
            if ((int) $stmt->fetchColumn() > 0) return;
        }

        Response::error('Forbidden: bukan pesanan Anda', 403);
    }

    private function authorizeManage(array $pesanan, array $user, string $newStatus): void
    {
        if ($user['role'] === 'admin') return;

        // Penjual boleh ubah status pesanan yang memuat produknya (kecuali ke status tertentu tertentu)
        if ($user['role'] === 'penjual') {
            $stmt = Database::get()->prepare(
                'SELECT COUNT(*) FROM detail_pesanan dp
                 JOIN produk p ON p.id_produk = dp.id_produk
                 WHERE dp.id_pesanan = ? AND p.id_penjual = ?'
            );
            $stmt->execute([(int) $pesanan['id_pesanan'], (int) $user['id_user']]);
            if ((int) $stmt->fetchColumn() > 0) {
                // Penjual tidak boleh membatalkan seenaknya jika sudah diproses? Tetap izinkan kelola pengiriman
                return;
            }
        }

        // Pembeli hanya boleh membatalkan pesanan miliknya sendiri saat masih menunggu
        if ((int) $pesanan['id_user'] === (int) $user['id_user']) {
            if ($newStatus === 'dibatalkan' && $pesanan['status'] === 'menunggu') {
                return;
            }
        }

        Response::error('Forbidden: tidak berwenang mengubah status pesanan', 403);
    }
}
