<?php

declare(strict_types=1);

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Autoload controllers (namespace App\Controllers)
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\Controllers\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\Controllers\\'));
    $file = __DIR__ . '/../src/Controllers/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Auth;
use App\Controllers\AuthController;
use App\Controllers\KategoriController;
use App\Controllers\KeranjangController;
use App\Controllers\PesananController;
use App\Controllers\ProdukController;
use App\Controllers\UserController;
use App\Response;
use App\Router;

$router = new Router();

// ── Auth ──────────────────────────────────────────
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login',    [AuthController::class, 'login']);
$router->get('/api/auth/me',        [AuthController::class, 'me'], true);

// ── Users ─────────────────────────────────────────
$router->get('/api/users',      [UserController::class, 'index'], true); // admin only below
$router->get('/api/users/{id}', [UserController::class, 'show'], true);
$router->put('/api/users/{id}', [UserController::class, 'update'], true);
$router->delete('/api/users/{id}', [UserController::class, 'destroy'], true);

// ── Kategori ──────────────────────────────────────
$router->get('/api/kategori',           [KategoriController::class, 'index']);
$router->get('/api/kategori/{id}',      [KategoriController::class, 'show']);
$router->post('/api/kategori',          [KategoriController::class, 'store'], true);
$router->put('/api/kategori/{id}',      [KategoriController::class, 'update'], true);
$router->delete('/api/kategori/{id}',   [KategoriController::class, 'destroy'], true);

// ── Produk ────────────────────────────────────────
$router->get('/api/produk',                 [ProdukController::class, 'index']);
$router->get('/api/produk/{id}',            [ProdukController::class, 'show']);
$router->post('/api/produk',                [ProdukController::class, 'store'], true);
$router->put('/api/produk/{id}',            [ProdukController::class, 'update'], true);
$router->delete('/api/produk/{id}',         [ProdukController::class, 'destroy'], true);
$router->get('/api/penjual/{id}/produk',    [ProdukController::class, 'byPenjual']);

// ── Keranjang ─────────────────────────────────────
$router->get('/api/keranjang',                     [KeranjangController::class, 'index'], true);
$router->post('/api/keranjang',                    [KeranjangController::class, 'store'], true);
$router->put('/api/keranjang/{id_produk}',         [KeranjangController::class, 'update'], true);
$router->delete('/api/keranjang/{id_produk}',      [KeranjangController::class, 'destroy'], true);
$router->delete('/api/keranjang',                  [KeranjangController::class, 'clear'], true);

// ── Pesanan / Checkout ────────────────────────────
$router->post('/api/checkout',                 [PesananController::class, 'checkout'], true);
$router->get('/api/pesanan',                   [PesananController::class, 'index'], true);
$router->get('/api/pesanan/{id}',              [PesananController::class, 'show'], true);
$router->put('/api/pesanan/{id}/status',       [PesananController::class, 'updateStatus'], true);

// ── Health ────────────────────────────────────────
$router->get('/api/health', function (): void {
    Response::success(['status' => 'ok', 'time' => date('c')], 'API berjalan');
});

// Admin gate untuk /api/users list
// (handled in controller via role check — wrap here)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && rtrim($path, '/') === '/api/users') {
    $user = Auth::currentUser();
    if (!$user) Response::error('Unauthorized', 401);
    if ($user['role'] !== 'admin') Response::error('Forbidden: khusus admin', 403);
}

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
} catch (PDOException $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}
