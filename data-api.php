<?php
/*
 * data-api.php
 * API lokal untuk mengelola data.json (tambah / hapus / list)
 * Tidak ada autentikasi karena data bukan data sensitif.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ini_set('display_errors', '0');

$DATA_FILE = __DIR__ . '/data.json';

/* ---------- helper ---------- */
function readData(string $file): array {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    if ($raw === false) return [];
    $parsed = json_decode($raw, true);
    if (!is_array($parsed['data'] ?? null)) return [];
    return $parsed['data'];
}

function writeData(string $file, array $data): bool {
    $json = json_encode(['data' => array_values($data)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $fp = fopen($file, 'c');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function ok(array $payload = []): void {
    echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- routing ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* GET ?action=list */
if ($method === 'GET') {
    ok(['data' => readData($DATA_FILE)]);
}

/* POST (add / delete) */
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) fail('Body JSON tidak valid');

    $action = $body['action'] ?? '';

    /* -- TAMBAH -- */
    if ($action === 'add') {
        $id       = trim($body['id']       ?? '');
        $judul    = trim($body['judul']    ?? '');
        $kategori = trim($body['kategori'] ?? '');
        $arab     = trim($body['arab']     ?? '');

        if ($id === '' || $judul === '' || $arab === '')
            fail('ID, judul, dan teks doa wajib diisi');

        // Bersihkan ID: hanya huruf, angka, tanda hubung, garis bawah
        $id = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $id);
        $id = preg_replace('/-{2,}/', '-', $id);
        $id = trim($id, '-');

        $data = readData($DATA_FILE);

        // Cek duplikasi ID
        foreach ($data as $item) {
            if (($item['id'] ?? '') === $id) fail('ID sudah ada, gunakan ID lain', 409);
        }

        $data[] = [
            'id'       => $id,
            'judul'    => $judul,
            'kategori' => $kategori,
            'arab'     => $arab,
            'aktif'    => 'TRUE',
        ];

        if (!writeData($DATA_FILE, $data)) fail('Gagal menyimpan ke data.json', 500);
        ok(['message' => 'Berhasil ditambah']);
    }

    /* -- EDIT -- */
    if ($action === 'update') {
        $id       = trim($body['id']       ?? '');
        $judul    = trim($body['judul']    ?? '');
        $kategori = trim($body['kategori'] ?? '');
        $arab     = trim($body['arab']     ?? '');

        if ($id === '' || $judul === '' || $arab === '')
            fail('ID, judul, dan teks doa wajib diisi');

        $data  = readData($DATA_FILE);
        $found = false;
        foreach ($data as &$item) {
            if (($item['id'] ?? '') === $id) {
                $item['judul']    = $judul;
                $item['kategori'] = $kategori;
                $item['arab']     = $arab;
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) fail('ID tidak ditemukan', 404);
        if (!writeData($DATA_FILE, $data)) fail('Gagal menyimpan ke data.json', 500);
        ok(['message' => 'Berhasil diupdate']);
    }

    /* -- HAPUS -- */
    if ($action === 'delete') {
        $id = trim($body['id'] ?? '');
        if ($id === '') fail('ID wajib diisi');

        $data     = readData($DATA_FILE);
        $filtered = array_values(array_filter($data, fn($item) => ($item['id'] ?? '') !== $id));

        if (count($filtered) === count($data)) fail('ID tidak ditemukan', 404);
        if (!writeData($DATA_FILE, $filtered)) fail('Gagal menyimpan ke data.json', 500);
        ok(['message' => 'Berhasil dihapus']);
    }

    fail('Action tidak dikenal');
}

fail('Method tidak diizinkan', 405);
