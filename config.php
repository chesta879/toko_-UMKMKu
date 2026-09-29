<?php

date_default_timezone_set('Asia/Jakarta');

if (!defined('APP_NAME')) {
    define('APP_NAME', 'PesanToko');
}

// Koneksi database, fungsi bantu, dan layout. Sesuaikan user/password jika berbeda.


session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();

$pdo = new PDO('mysql:host=localhost;dbname=toko_pesanan;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp' . number_format((int)$n, 0, ',', '.'); }

// ---- CSRF ----
function csrf_field() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $t = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(419);
        exit('Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// ---- Auth ----
function user() { return $_SESSION['u'] ?? null; }
function need($role = null) {
    if (empty($_SESSION['u'])) { header('Location: login.php'); exit; }
    if ($role && $_SESSION['u']['hak_akses'] !== $role) {
        http_response_code(403);
        exit('Akses ditolak: halaman khusus ' . e($role));
    }
}

// ---- Pesan singkat (toast). Awali dengan "Gagal" untuk tampilan galat ----
function flash($m = null) {
    if ($m !== null) { $_SESSION['flash'] = $m; return; }
    $f = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $f;
}

// Toko dianggap buka jika status 'buka' dan jam sekarang berada di rentang buka-tutup
function jam_normalisasi($v) {
    $v = trim((string)$v);

    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $v, $m)) {
        $detik = $m[3] ?? '00';
        return $m[1] . ':' . $m[2] . ':' . $detik;
    }

    return null;
}

function toko_buka(array $t) {
    if (($t['status_buka'] ?? '') !== 'buka') return false;
    $buka = jam_normalisasi($t['jam_buka'] ?? '');
    $tutup = jam_normalisasi($t['jam_tutup'] ?? '');
    if ($buka === null || $tutup === null) return false; // jam tidak valid -> anggap tutup
    $now = date('H:i:s');
    return $buka <= $tutup
        ? ($now >= $buka && $now < $tutup)
        : ($now >= $buka || $now < $tutup); // buka lintas tengah malam
}

// Nomor WhatsApp internasional dari nomor lokal (0812... -> 62812...)
function wa_nomor($kontak) {
    $d = preg_replace('/\D+/', '', (string)$kontak);

    if ($d !== '' && $d[0] === '0') {
        $d = '62' . substr($d, 1);
    }

    return strlen($d) >= 9 ? $d : '';
}
// Ikon garis (SVG inline)
function icon($n, $s = 18) {
    static $p = [
        'bag'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'box'    => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'tag'    => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'sliders'=> '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'store'  => '<path d="M3 9l1.5-5h15L21 9M3 9h18M3 9v11h18V9M9 20v-6h6v6"/>',
        'out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'cart'   => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'minus'  => '<path d="M5 12h14"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pin'    => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'chat'   => '<path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.7-5.2A8.5 8.5 0 1 1 21 12z"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
        'cash'   => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    ];
    return '<svg class="i" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

/**
 * Buka halaman. $mode: 'staff' (sidebar), 'public' (halaman pelanggan), 'auth' (login).
 * $ctx: untuk mode 'public', array data toko.
 */
function top($title, $mode = 'staff', $ctx = null, $bodyAttr = '') {
    global $pdo;
    $GLOBALS['__mode'] = $mode;
    $fav = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%23073f55'/%3E%3Ccircle cx='16' cy='16' r='6' fill='%23ffb703'/%3E%3C/svg%3E";
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="theme-color" content="#073f55"><title>' . e($title) . '</title><link rel="icon" href="' . $fav . '">'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">'
       . '<link rel="stylesheet" href="assets/app.css?v=3"></head><body ' . $bodyAttr . '>';

    $f = flash();
    echo '<div id="toast" class="toast' . ($f ? ' show' . (strpos($f, 'Gagal') === 0 ? ' err' : '') : '') . '" role="status" aria-live="polite">' . e($f) . '</div>';

    if ($mode === 'auth') return;

    if ($mode === 'public') {
        echo '<header class="pub"><div class="in"><a class="lg" href="index.php"><span class="mark">' . icon('store', 18) . '</span><span>' . e($ctx['nama_toko'] ?? APP_NAME) . '</span></a>'
           . '<a class="sl" href="login.php">Masuk staf</a></div></header><main class="wrap">';
        return;
    }

    // staf
    $u = user();
    $nav = [['pesanan.php', 'bag', 'Pesanan'], ['produk.php', 'box', 'Produk']];
    if ($u['hak_akses'] === 'admin') {
        $nav[] = ['kategori.php', 'tag', 'Kategori'];
        $nav[] = ['toko.php', 'sliders', 'Pengaturan toko'];
        $nav[] = ['pengguna.php', 'users', 'Pengguna'];
    }
    $nav[] = ['index.php', 'store', 'Lihat toko'];
    $s = $pdo->prepare('SELECT nama_toko FROM toko WHERE id_toko=?'); $s->execute([$u['id_toko']]);
    $tn = $s->fetchColumn() ?: '';
    $cur = basename($_SERVER['SCRIPT_NAME']);

    echo '<div class="shell"><aside class="side"><a class="logo" href="pesanan.php"><span class="mk">' . icon('store', 20) . '</span><span><b>' . e(APP_NAME) . '</b><small>' . e($tn) . '</small></span></a><nav class="sn">';
    foreach ($nav as [$l, $ic, $lbl]) echo '<a class="nl' . ($cur === $l ? ' on' : '') . '" href="' . $l . '">' . icon($ic) . '<span>' . e($lbl) . '</span></a>';
    echo '</nav><div class="me"><span class="av">' . e(mb_strtoupper(mb_substr($u['nama'], 0, 1))) . '</span><div><b>' . e($u['nama']) . '</b><small>' . e($u['hak_akses']) . '</small></div>'
       . '<a href="logout.php" title="Keluar" aria-label="Keluar">' . icon('out') . '</a></div></aside>'
       . '<main class="content"><div class="pagehead"><h1>' . e($title) . '</h1></div>';
}

function bottom() {
    $m = $GLOBALS['__mode'] ?? 'staff';
    if ($m === 'staff') echo '</main></div>';
    elseif ($m === 'public') echo '</main>';
    echo '<script src="assets/app.js?v=3"></script></body></html>';
}
