<?php

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Request;
use App\Response;

class AuthController
{
    public function register(array $params): void
    {
        $nama    = trim((string) Request::input('nama', ''));
        $email   = trim((string) Request::input('email', ''));
        $password = (string) Request::input('password', '');
        $noHp    = trim((string) Request::input('no_hp', ''));
        $alamat  = trim((string) Request::input('alamat', ''));
        $role    = (string) Request::input('role', 'pembeli');

        $errors = [];
        if ($nama === '')    $errors['nama'] = 'nama wajib diisi';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'email tidak valid';
        if (strlen($password) < 6) $errors['password'] = 'password minimal 6 karakter';
        if (!in_array($role, ['pembeli', 'penjual'], true)) {
            $errors['role'] = 'role hanya boleh pembeli atau penjual';
        }
        if ($errors) {
            Response::error('Validasi gagal', 422, $errors);
        }

        $pdo = Database::get();

        $stmt = $pdo->prepare('SELECT id_user FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('Email sudah terdaftar', 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (nama, email, password, no_hp, alamat, role) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $nama,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $noHp ?: null,
            $alamat ?: null,
            $role,
        ]);

        $id = (int) $pdo->lastInsertId();
        $token = Auth::issueToken($id);

        Response::success([
            'user'  => $this->findUser($id),
            'token' => $token,
        ], 'Registrasi berhasil', 201);
    }

    public function login(array $params): void
    {
        $email    = trim((string) Request::input('email', ''));
        $password = (string) Request::input('password', '');

        if ($email === '' || $password === '') {
            Response::error('email dan password wajib diisi', 422);
        }

        $stmt = Database::get()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            Response::error('Email atau password salah', 401);
        }
        if ($user['status'] !== 'aktif') {
            Response::error('Akun tidak aktif', 403);
        }

        Response::success([
            'user'  => $this->publicUser($user),
            'token' => Auth::issueToken((int) $user['id_user']),
        ], 'Login berhasil');
    }

    public function me(array $params, ?array $user): void
    {
        Response::success($user, 'OK');
    }

    private function findUser(int $id): array
    {
        $stmt = Database::get()->prepare('SELECT id_user, nama, email, no_hp, alamat, role, status, created_at FROM users WHERE id_user = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function publicUser(array $user): array
    {
        unset($user['password']);
        return $user;
    }
}
