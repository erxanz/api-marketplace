<?php

namespace App\Controllers;

use App\Database;
use App\Request;
use App\Response;

class KategoriController
{
    public function index(array $params): void
    {
        $stmt = Database::get()->query(
            'SELECT k.*,
                    (SELECT COUNT(*) FROM produk p WHERE p.id_kategori = k.id_kategori AND p.status = "aktif") AS jumlah_produk
             FROM kategori k
             ORDER BY k.id_kategori'
        );
        Response::success($stmt->fetchAll(), 'Daftar kategori');
    }

    public function show(array $params): void
    {
        $stmt = Database::get()->prepare('SELECT * FROM kategori WHERE id_kategori = ?');
        $stmt->execute([(int) $params['id']]);
        $kategori = $stmt->fetch();

        if (!$kategori) {
            Response::notFound('Kategori tidak ditemukan');
        }
        Response::success($kategori, 'Detail kategori');
    }

    public function store(array $params, ?array $user): void
    {
        $nama      = trim((string) Request::input('nama_kategori', ''));
        $deskripsi = trim((string) Request::input('deskripsi', ''));

        $errors = [];
        if ($nama === '') $errors['nama_kategori'] = 'nama kategori wajib diisi';
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT id_kategori FROM kategori WHERE nama_kategori = ?');
        $stmt->execute([$nama]);
        if ($stmt->fetch()) {
            Response::error('Nama kategori sudah ada', 409);
        }

        $stmt = $pdo->prepare('INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)');
        $stmt->execute([$nama, $deskripsi ?: null]);

        Response::success($this->find((int) $pdo->lastInsertId()), 'Kategori berhasil ditambahkan', 201);
    }

    public function update(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        if (!$this->find($id)) Response::notFound('Kategori tidak ditemukan');

        $nama      = trim((string) Request::input('nama_kategori', ''));
        $deskripsi = Request::input('deskripsi');

        $errors = [];
        if ($nama === '') $errors['nama_kategori'] = 'nama kategori wajib diisi';
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT id_kategori FROM kategori WHERE nama_kategori = ? AND id_kategori != ?');
        $stmt->execute([$nama, $id]);
        if ($stmt->fetch()) {
            Response::error('Nama kategori sudah ada', 409);
        }

        $stmt = $pdo->prepare('UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id_kategori = ?');
        $stmt->execute([$nama, $deskripsi ?? null, $id]);

        Response::success($this->find($id), 'Kategori berhasil diperbarui');
    }

    public function destroy(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        if (!$this->find($id)) Response::notFound('Kategori tidak ditemukan');

        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM produk WHERE id_kategori = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            Response::error('Kategori tidak bisa dihapus karena masih dipakai produk', 409);
        }

        $stmt = $pdo->prepare('DELETE FROM kategori WHERE id_kategori = ?');
        $stmt->execute([$id]);

        Response::success(null, 'Kategori berhasil dihapus');
    }

    private function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM kategori WHERE id_kategori = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
