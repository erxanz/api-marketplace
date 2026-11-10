<?php

namespace App\Controllers;

use App\Database;
use App\Request;
use App\Response;

class UserController
{
    public function index(array $params, ?array $user): void
    {
        $stmt = Database::get()->query(
            'SELECT id_user, nama, email, no_hp, alamat, role, status, created_at
             FROM users
             ORDER BY id_user'
        );
        Response::success($stmt->fetchAll(), 'Daftar users');
    }

    public function show(array $params, ?array $user): void
    {
        $id = (int) $params['id'];

        if ($user['role'] !== 'admin' && (int) $user['id_user'] !== $id) {
            Response::error('Forbidden', 403);
        }

        $stmt = Database::get()->prepare(
            'SELECT id_user, nama, email, no_hp, alamat, role, status, created_at
             FROM users WHERE id_user = ?'
        );
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) Response::notFound('User tidak ditemukan');
        Response::success($target, 'Detail user');
    }

    public function update(array $params, ?array $user): void
    {
        $id = (int) $params['id'];
        $isAdmin = $user['role'] === 'admin';

        if (!$isAdmin && (int) $user['id_user'] !== $id) {
            Response::error('Forbidden', 403);
        }

        $stmt = Database::get()->prepare(
            'SELECT id_user, nama, email, no_hp, alamat, role, status FROM users WHERE id_user = ?'
        );
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) Response::notFound('User tidak ditemukan');

        $nama    = trim((string) Request::input('nama', $target['nama']));
        $noHp    = Request::input('no_hp', $target['no_hp']);
        $alamat  = Request::input('alamat', $target['alamat']);
        $password = Request::input('password');

        $errors = [];
        if ($nama === '') $errors['nama'] = 'nama wajib diisi';
        if ($password !== null && strlen((string) $password) < 6) {
            $errors['password'] = 'password minimal 6 karakter';
        }
        if ($errors) Response::error('Validasi gagal', 422, $errors);

        $fields = ['nama = ?', 'no_hp = ?', 'alamat = ?'];
        $bind = [$nama, $noHp, $alamat];

        if ($password !== null && $password !== '') {
            $fields[] = 'password = ?';
            $bind[] = password_hash((string) $password, PASSWORD_DEFAULT);
        }

        // Admin bisa ubah role & status
        if ($isAdmin) {
            $role = Request::input('role');
            $status = Request::input('status');
            if ($role !== null) {
                if (!in_array($role, ['pembeli', 'penjual', 'admin'], true)) {
                    Response::error('Role tidak valid', 422);
                }
                $fields[] = 'role = ?';
                $bind[] = $role;
            }
            if ($status !== null) {
                if (!in_array($status, ['aktif', 'nonaktif'], true)) {
                    Response::error('Status tidak valid', 422);
                }
                $fields[] = 'status = ?';
                $bind[] = $status;
            }
        }

        $bind[] = $id;
        Database::get()
            ->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id_user = ?')
            ->execute($bind);

        $stmt = Database::get()->prepare(
            'SELECT id_user, nama, email, no_hp, alamat, role, status, created_at
             FROM users WHERE id_user = ?'
        );
        $stmt->execute([$id]);

        Response::success($stmt->fetch(), 'User berhasil diperbarui');
    }

    public function destroy(array $params, ?array $user): void
    {
        $id = (int) $params['id'];

        if ((int) $user['id_user'] === $id) {
            Response::error('Tidak bisa menghapus akun sendiri', 409);
        }

        $stmt = Database::get()->prepare('SELECT id_user FROM users WHERE id_user = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::notFound('User tidak ditemukan');

        $pdo = Database::get();
        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM produk WHERE id_penjual = ?) AS produk,
                (SELECT COUNT(*) FROM pesanan WHERE id_user = ?) AS pesanan'
        );
        $stmt->execute([$id, $id]);
        $ref = $stmt->fetch();

        if ((int) $ref['produk'] > 0 || (int) $ref['pesanan'] > 0) {
            // Nonaktifkan saja agar data transaksi tidak rusak
            $pdo->prepare('UPDATE users SET status = "nonaktif" WHERE id_user = ?')->execute([$id]);
            Response::success(null, 'User masih memiliki data transaksi, akun dinonaktifkan');
        }

        $pdo->prepare('DELETE FROM users WHERE id_user = ?')->execute([$id]);
        Response::success(null, 'User berhasil dihapus');
    }
}
