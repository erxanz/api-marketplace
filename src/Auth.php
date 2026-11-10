<?php

namespace App;

class Auth
{
    private static ?array $config = null;

    private static function config(): array
    {
        if (!self::$config) {
            self::$config = require __DIR__ . '/../config/config.php';
        }
        return self::$config['auth'];
    }

    public static function issueToken(int $userId): string
    {
        $cfg = self::config();
        $payload = $userId . '|' . (time() + $cfg['token_ttl']);
        $sig = hash_hmac('sha256', $payload, $cfg['secret']);
        return base64_encode($payload . '|' . $sig);
    }

    public static function verifyToken(string $token): ?int
    {
        $cfg = self::config();
        $raw = base64_decode($token, true);
        if ($raw === false) {
            return null;
        }

        $parts = explode('|', $raw);
        if (count($parts) !== 3) {
            return null;
        }

        [$userId, $expires, $sig] = $parts;
        $expected = hash_hmac('sha256', $userId . '|' . $expires, $cfg['secret']);

        if (!hash_equals($expected, $sig)) {
            return null;
        }
        if ((int) $expires < time()) {
            return null;
        }
        if (!ctype_digit($userId)) {
            return null;
        }

        return (int) $userId;
    }

    public static function currentUser(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return null;
        }

        $userId = self::verifyToken($m[1]);
        if ($userId === null) {
            return null;
        }

        $stmt = Database::get()->prepare('SELECT id_user, nama, email, no_hp, alamat, role, status, created_at FROM users WHERE id_user = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function requireUser(): array
    {
        $user = self::currentUser();
        if (!$user) {
            Response::error('Unauthorized: token tidak valid atau sudah kedaluwarsa', 401);
        }
        if ($user['status'] !== 'aktif') {
            Response::error('Akun tidak aktif', 403);
        }
        return $user;
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::requireUser();
        if (!in_array($user['role'], $roles, true)) {
            Response::error('Forbidden: akses ditolak', 403);
        }
        return $user;
    }
}
