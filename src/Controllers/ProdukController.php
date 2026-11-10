<?php

namespace App\Controllers;

use App\Database;
use App\Request;
use App\Response;

class ProdukController
{
    public function index(array $params): void
    {
        $pdo = Database::get();

        $where = ['p.status = "aktif"'];
        $bind  = [];

        $q = Request::query('q');
        if ($q !== null && $q !== '') {
            $where[] = '(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
            $like = '%' . $q . '%';
            $bind[] = $like;
            $bind[] = $like;
        }

        $kategori = Request::query('id_kategori');
        if ($kategori !== null && $kategori !== '') {
            $where[] = 'p.id_kategori = ?';
            $bind[] = (int) $kategori;
        }

        $penjual = Request::query('id_penjual');
        if ($penjual !== null && $penjual !== '') {
            $where[] = 'p.id_penjual = ?';
            $bind[] = (int) $penjual;
        }

        // Hanya tampil produk penjual aktif
        $where[] = 'u.status = "aktif"';

        $page = max(1, (int) Request::query('page', 1));
        $perPage = min(50, max(1, (int) Request::query('per_page', 12)));
        $offset = ($page - 1) * $perPage;

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM produk p JOIN users u ON u.id_user = p.id_penjual WHERE $whereSql"
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT p.*, k.nama_kategori, u.nama AS nama_penjual
                FROM produk p
                JOIN kategori k ON k.id_kategori = p.id_kategori
                JOIN users u ON u.id_user = p.id_penjual
                WHERE $whereSql
                ORDER BY p.created_at DESC, p.id_produk DESC
                LIMIT $perPage OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);

        Response::success([
            'items'     => $stmt->fetchAll(),
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ], 'Daftar produk');
    }

    public function show(array $params): void
    {
        $stmt = Database::get()->prepare(
            'SELECT p.*, k.nama_kategori, u.nama AS nama_penjual, u.email AS email_penjual, u.no_hp AS no_hp_penjual
             FROM produk p
             JOIN kategori k ON k.id_kategori = p.id_kategori
             JOIN users u ON u.id_user = p.id_penjual
             WHERE p.id_produk = ?'
        );
        $stmt->execute([(int) $params['id']]);
        $produk = $stmt->fetch();

        if (!$produk) {
            Response::notFound('Produk tidak ditemukan');
        }
        Response::success($produk, 'Detail produk');
    }

    public function store(array $params, ?array $user): void
    {
        $idPenjual = (int) $user['id_user'];

        $nama       = trim((string) Request::input('nama_produk', ''));
        $deskripsi  = trim((string) Request::input('deskripsi', ''));
        $idKategori = (int) Request::input('id_kategori', 0);
        $harga      = Request::input('harga');
        $stok       = (int) Request::input('stok', 0);
        $gambar     = trim((string) Request::input('gambar', ''));
        $status     = (string) Request::input('status', 'aktif');

        $errors = [];
        if ($nama === '') $errors['nama_produk'] = 'nama produk wajib diisi';
        if ($harga === null || !is_numeric($harga) || (float) $harga < 0) $errors['harga'] = 'harga harus angka >= 0';
        if ($stok < 0) $errors['stok'] = 'stok tidak boleh negatif';
        if (!in_array($status, ['aktif', 'nonaktif'], true)) $errors['status'] = 'status harus aktif/nonaktif';
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $pdo = Database::get();

        $stmt = $pdo->prepare('SELECT id_kategori FROM kategori WHERE id_kategori = ?');
        $stmt->execute([$idKategori]);
        if (!$stmt->fetch()) {
            Response::error('Kategori tidak ditemukan', 422, ['id_kategori' => 'id_kategori tidak valid']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok, gambar, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $idPenjual,
            $idKategori,
            $nama,
            $deskripsi ?: null,
            $harga,
            $stok,
            $gambar ?: null,
            $status,
        ]);

        Response::success($this->find((int) $pdo->lastInsertId()), 'Produk berhasil ditambahkan', 201);
    }

    public function update(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        $produk = $this->find($id);
        if (!$produk) Response::notFound('Produk tidak ditemukan');

        $this->authorizeOwner($produk, $user);

        $nama       = trim((string) Request::input('nama_produk', $produk['nama_produk']));
        $deskripsi  = Request::input('deskripsi', $produk['deskripsi']);
        $idKategori = (int) Request::input('id_kategori', $produk['id_kategori']);
        $harga      = Request::input('harga', $produk['harga']);
        $stok       = (int) Request::input('stok', $produk['stok']);
        $gambar     = Request::input('gambar', $produk['gambar']);
        $status     = (string) Request::input('status', $produk['status']);

        $errors = [];
        if ($nama === '') $errors['nama_produk'] = 'nama produk wajib diisi';
        if (!is_numeric($harga) || (float) $harga < 0) $errors['harga'] = 'harga harus angka >= 0';
        if ($stok < 0) $errors['stok'] = 'stok tidak boleh negatif';
        if (!in_array($status, ['aktif', 'nonaktif'], true)) $errors['status'] = 'status harus aktif/nonaktif';
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $pdo = Database::get();

        $stmt = $pdo->prepare('SELECT id_kategori FROM kategori WHERE id_kategori = ?');
        $stmt->execute([$idKategori]);
        if (!$stmt->fetch()) {
            Response::error('Kategori tidak ditemukan', 422, ['id_kategori' => 'id_kategori tidak valid']);
        }

        $stmt = $pdo->prepare(
            'UPDATE produk SET id_kategori = ?, nama_produk = ?, deskripsi = ?, harga = ?, stok = ?, gambar = ?, status = ?
             WHERE id_produk = ?'
        );
        $stmt->execute([
            $idKategori,
            $nama,
            $deskripsi ?: null,
            $harga,
            $stok,
            $gambar ?: null,
            $status,
            $id,
        ]);

        Response::success($this->find($id), 'Produk berhasil diperbarui');
    }

    public function destroy(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        $produk = $this->find($id);
        if (!$produk) Response::notFound('Produk tidak ditemukan');

        $this->authorizeOwner($produk, $user);

        $pdo = Database::get();

        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM detail_keranjang WHERE id_produk = ?) AS in_keranjang,
                (SELECT COUNT(*) FROM detail_pesanan WHERE id_produk = ?) AS in_pesanan'
        );
        $stmt->execute([$id, $id]);
        $ref = $stmt->fetch();

        if ((int) $ref['in_keranjang'] > 0 || (int) $ref['in_pesanan'] > 0) {
            // Nonaktifkan saja agar riwayat tetap ada
            $pdo->prepare('UPDATE produk SET status = "nonaktif" WHERE id_produk = ?')->execute([$id]);
            Response::success($this->find($id), 'Produk masih dipakai, status diubah menjadi nonaktif');
        }

        $pdo->prepare('DELETE FROM produk WHERE id_produk = ?')->execute([$id]);
        Response::success(null, 'Produk berhasil dihapus');
    }

    public function byPenjual(array $params, ?array $user): void
    {
        $idPenjual = (int) $params['id'];
        $stmt = Database::get()->prepare(
            'SELECT p.*, k.nama_kategori
             FROM produk p
             JOIN kategori k ON k.id_kategori = p.id_kategori
             WHERE p.id_penjual = ?
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$idPenjual]);
        Response::success($stmt->fetchAll(), 'Daftar produk penjual');
    }

    private function find(int $id): ?array
    {
        $stmt = Database::get()->prepare(
            'SELECT p.*, k.nama_kategori, u.nama AS nama_penjual
             FROM produk p
             JOIN kategori k ON k.id_kategori = p.id_kategori
             JOIN users u ON u.id_user = p.id_penjual
             WHERE p.id_produk = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function authorizeOwner(array $produk, array $user): void
    {
        if ($user['role'] === 'admin') {
            return;
        }
        if ((int) $produk['id_penjual'] !== (int) $user['id_user']) {
            Response::error('Forbidden: bukan produk Anda', 403);
        }
    }
}
