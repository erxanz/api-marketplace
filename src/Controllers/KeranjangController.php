<?php

namespace App\Controllers;

use App\Database;
use App\Request;
use App\Response;

class KeranjangController
{
    public function index(array $params, ?array $user): void
    {
        $pdo = Database::get();
        $idKeranjang = $this->activeCartId((int) $user['id_user'], false);

        if (!$idKeranjang) {
            Response::success([
                'id_keranjang' => null,
                'items' => [],
                'total' => 0,
                'jumlah_item' => 0,
            ], 'Keranjang kosong');
        }

        $stmt = $pdo->prepare(
            'SELECT dk.id_detail_keranjang, dk.jumlah,
                    p.id_produk, p.nama_produk, p.harga, p.stok, p.gambar, p.status AS status_produk,
                    k.nama_kategori,
                    (dk.jumlah * p.harga) AS subtotal
             FROM detail_keranjang dk
             JOIN produk p ON p.id_produk = dk.id_produk
             JOIN kategori k ON k.id_kategori = p.id_kategori
             WHERE dk.id_keranjang = ?
             ORDER BY dk.id_detail_keranjang'
        );
        $stmt->execute([$idKeranjang]);
        $items = $stmt->fetchAll();

        $total = 0;
        $jumlahItem = 0;
        foreach ($items as $item) {
            $total += (float) $item['subtotal'];
            $jumlahItem += (int) $item['jumlah'];
        }

        Response::success([
            'id_keranjang' => $idKeranjang,
            'items'        => $items,
            'total'        => $total,
            'jumlah_item'  => $jumlahItem,
        ], 'Isi keranjang');
    }

    public function store(array $params, ?array $user): void
    {
        $idProduk = (int) Request::input('id_produk', 0);
        $jumlah   = (int) Request::input('jumlah', 1);

        $errors = [];
        if ($idProduk <= 0) $errors['id_produk'] = 'id_produk wajib diisi';
        if ($jumlah < 1) $errors['jumlah'] = 'jumlah minimal 1';
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $pdo = Database::get();

        $stmt = $pdo->prepare('SELECT id_produk, nama_produk, harga, stok, status FROM produk WHERE id_produk = ?');
        $stmt->execute([$idProduk]);
        $produk = $stmt->fetch();

        if (!$produk) Response::notFound('Produk tidak ditemukan');
        if ($produk['status'] !== 'aktif') Response::error('Produk tidak tersedia', 409);

        $idKeranjang = $this->activeCartId((int) $user['id_user'], true);

        // Gabung jika sudah ada
        $stmt = $pdo->prepare('SELECT id_detail_keranjang, jumlah FROM detail_keranjang WHERE id_keranjang = ? AND id_produk = ?');
        $stmt->execute([$idKeranjang, $idProduk]);
        $existing = $stmt->fetch();

        $totalJumlah = $jumlah + ($existing ? (int) $existing['jumlah'] : 0);
        if ($totalJumlah > (int) $produk['stok']) {
            Response::error('Stok tidak mencukupi', 409, [
                'stok_tersedia' => (int) $produk['stok'],
                'diminta'       => $totalJumlah,
            ]);
        }

        if ($existing) {
            $pdo->prepare('UPDATE detail_keranjang SET jumlah = ? WHERE id_detail_keranjang = ?')
                ->execute([$totalJumlah, $existing['id_detail_keranjang']]);
        } else {
            $pdo->prepare('INSERT INTO detail_keranjang (id_keranjang, id_produk, jumlah) VALUES (?, ?, ?)')
                ->execute([$idKeranjang, $idProduk, $jumlah]);
        }

        Response::success(null, 'Produk masuk keranjang', 201);
    }

    public function update(array $params, ?array $user): void
    {
        $idProduk = (int) $params['id_produk'];
        $jumlah   = (int) Request::input('jumlah', 0);

        if ($jumlah < 1) Response::error('jumlah minimal 1', 422);

        $pdo = Database::get();
        $idKeranjang = $this->activeCartId((int) $user['id_user'], false);
        if (!$idKeranjang) Response::notFound('Item tidak ada di keranjang');

        $stmt = $pdo->prepare(
            'SELECT dk.id_detail_keranjang, p.stok, p.status
             FROM detail_keranjang dk
             JOIN produk p ON p.id_produk = dk.id_produk
             WHERE dk.id_keranjang = ? AND dk.id_produk = ?'
        );
        $stmt->execute([$idKeranjang, $idProduk]);
        $item = $stmt->fetch();

        if (!$item) Response::notFound('Item tidak ada di keranjang');
        if ($item['status'] !== 'aktif') Response::error('Produk tidak tersedia', 409);
        if ($jumlah > (int) $item['stok']) {
            Response::error('Stok tidak mencukupi', 409, ['stok_tersedia' => (int) $item['stok']]);
        }

        $pdo->prepare('UPDATE detail_keranjang SET jumlah = ? WHERE id_detail_keranjang = ?')
            ->execute([$jumlah, $item['id_detail_keranjang']]);

        Response::success(null, 'Keranjang diperbarui');
    }

    public function destroy(array $params, ?array $user): void
    {
        $idProduk = (int) $params['id_produk'];
        $idKeranjang = $this->activeCartId((int) $user['id_user'], false);
        if (!$idKeranjang) Response::notFound('Keranjang kosong');

        $stmt = Database::get()->prepare('DELETE FROM detail_keranjang WHERE id_keranjang = ? AND id_produk = ?');
        $stmt->execute([$idKeranjang, $idProduk]);

        if ($stmt->rowCount() === 0) {
            Response::notFound('Item tidak ada di keranjang');
        }

        Response::success(null, 'Item dihapus dari keranjang');
    }

    public function clear(array $params, ?array $user): void
    {
        $idKeranjang = $this->activeCartId((int) $user['id_user'], false);
        if ($idKeranjang) {
            Database::get()->prepare('DELETE FROM detail_keranjang WHERE id_keranjang = ?')->execute([$idKeranjang]);
        }
        Response::success(null, 'Keranjang dikosongkan');
    }

    private function activeCartId(int $idUserId, bool $create): ?int
    {
        $pdo = Database::get();

        $stmt = $pdo->prepare('SELECT id_keranjang FROM keranjang WHERE id_user = ? AND status = "aktif" ORDER BY id_keranjang DESC LIMIT 1');
        $stmt->execute([$idUserId]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id_keranjang'];
        }

        if ($create) {
            $pdo->prepare('INSERT INTO keranjang (id_user, status) VALUES (?, "aktif")')->execute([$idUserId]);
            return (int) $pdo->lastInsertId();
        }

        return null;
    }
}
