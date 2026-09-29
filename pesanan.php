<?php
// Staf: papan pesanan (baru -> diproses -> selesai / batal)
require 'config.php'; need(); csrf_check();
$tid = user()['id_toko']; $uid = user()['id_pengguna'];

// aksi => [status asal yang diizinkan, status tujuan]
$flow = ['proses' => [['baru'], 'diproses'], 'selesai' => [['diproses'], 'selesai'], 'batal' => [['baru', 'diproses'], 'batal']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    try {
        if (!isset($flow[$a])) throw new Exception('Aksi tidak dikenal');
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT status_pesanan FROM pesanan WHERE id_pesanan=? AND id_toko=? FOR UPDATE');
        $s->execute([$id, $tid]); $cur = $s->fetchColumn();
        if ($cur === false) throw new Exception('Pesanan tidak ditemukan');
        if (!in_array($cur, $flow[$a][0], true)) throw new Exception("Pesanan berstatus $cur tidak dapat diubah dengan aksi ini");
        $pdo->prepare('UPDATE pesanan SET status_pesanan=?, id_pengguna=? WHERE id_pesanan=?')->execute([$flow[$a][1], $uid, $id]);
        if ($a === 'batal') // kembalikan stok
            $pdo->prepare('UPDATE produk p JOIN detail_pesanan d ON d.id_produk=p.id_produk SET p.stok=p.stok+d.jumlah WHERE d.id_pesanan=?')->execute([$id]);
        $pdo->commit();
        flash("Pesanan #$id sekarang " . $flow[$a][1]);
    } catch (Exception $x) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Gagal: ' . $x->getMessage());
    }
    header('Location: pesanan.php'); exit;
}

function ago($t) {
    $d = time() - strtotime($t);
    if ($d < 60) return 'baru saja';
    if ($d < 3600) return floor($d / 60) . ' menit lalu';
    if ($d < 86400) return floor($d / 3600) . ' jam lalu';
    return date('d/m H:i', strtotime($t));
}

$statuses = ['baru', 'diproses', 'selesai', 'batal'];
$c = $pdo->prepare('SELECT status_pesanan, COUNT(*) n FROM pesanan WHERE id_toko=? GROUP BY status_pesanan');
$c->execute([$tid]); $count = array_fill_keys($statuses, 0);
foreach ($c as $r) $count[$r['status_pesanan']] = (int)$r['n'];
$s = $pdo->prepare("SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE id_toko=? AND status_pesanan='selesai' AND DATE(waktu_pesanan)=CURDATE()");
$s->execute([$tid]); $omzet = (int)$s->fetchColumn();

$q = 'SELECT p.*, u.nama AS petugas FROM pesanan p LEFT JOIN pengguna u ON u.id_pengguna=p.id_pengguna WHERE p.id_toko=? AND p.status_pesanan IN ';
$s = $pdo->prepare($q . "('baru','diproses') ORDER BY p.id_pesanan LIMIT 100"); $s->execute([$tid]);
$open = ['baru' => [], 'diproses' => []];
foreach ($s as $o) $open[$o['status_pesanan']][] = $o;
$s = $pdo->prepare($q . "('selesai','batal') ORDER BY p.id_pesanan DESC LIMIT 30"); $s->execute([$tid]);
$history = $s->fetchAll();

$ids = array_merge(array_column($open['baru'], 'id_pesanan'), array_column($open['diproses'], 'id_pesanan'));
$detail = [];
if ($ids) {
    $s = $pdo->prepare('SELECT d.id_pesanan, d.jumlah, pr.nama_produk FROM detail_pesanan d JOIN produk pr ON pr.id_produk=d.id_produk WHERE d.id_pesanan IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $s->execute($ids);
    foreach ($s as $d) $detail[$d['id_pesanan']][] = $d;
}

function tombol($act, $label, $ic, $id, $cls = '', $confirm = '') {
    return '<form method="post"' . ($confirm ? ' onsubmit="return confirm(\'' . $confirm . '\')"' : '') . '>' . csrf_field()
         . '<input type="hidden" name="a" value="' . $act . '"><input type="hidden" name="id" value="' . e($id) . '"><button class="' . $cls . '">' . icon($ic, 15) . $label . '</button></form>';
}

top('Pesanan', 'staff', null, 'data-refresh="60"');
echo '<div class="stats">'
   . '<div class="card stat t-sun"><span class="ic">' . icon('clock', 22) . '</span><div><b>' . $count['baru'] . '</b><span>Menunggu diproses</span></div></div>'
   . '<div class="card stat"><span class="ic">' . icon('bag', 22) . '</span><div><b>' . $count['diproses'] . '</b><span>Sedang diproses</span></div></div>'
   . '<div class="card stat t-ok"><span class="ic">' . icon('check', 22) . '</span><div><b>' . $count['selesai'] . '</b><span>Pesanan selesai</span></div></div>'
   . '<div class="card stat"><span class="ic">' . icon('cash', 22) . '</span><div><b>' . rupiah($omzet) . '</b><span>Omzet selesai hari ini</span></div></div></div>';

echo '<div class="board">';
foreach (['baru' => ['Pesanan baru', 'proses', 'Proses', 'bag'], 'diproses' => ['Sedang diproses', 'selesai', 'Tandai selesai', 'check']] as $st => [$judul, $next, $lbl, $ic]) {
    echo '<section class="col"><h2>' . $judul . '<span class="n">' . count($open[$st]) . '</span></h2><div class="stack">';
    foreach ($open[$st] as $o) {
        echo '<article class="card oc"><div class="oc-h"><div><b>' . e($o['nama_pelanggan']) . '</b><small>#' . e($o['id_pesanan']) . ' · ' . e(ago($o['waktu_pesanan'])) . '</small></div>'
           . '<span class="badge s-' . e($st) . '">' . e($st) . '</span></div><ul>';
        foreach ($detail[$o['id_pesanan']] ?? [] as $d) echo '<li><span>' . e($d['nama_produk']) . '</span><span>×' . e($d['jumlah']) . '</span></li>';
        echo '</ul><div class="oc-f"><span class="price">' . rupiah($o['total_harga']) . '</span><div class="acts">'
           . tombol('batal', 'Batalkan', 'x', $o['id_pesanan'], 'bd', 'Batalkan pesanan ini? Stok akan dikembalikan.')
           . tombol($next, $lbl, $ic, $o['id_pesanan']) . '</div></div></article>';
    }
    if (!$open[$st]) echo '<div class="empty">' . ($st === 'baru' ? 'Belum ada pesanan baru. Halaman ini memuat ulang otomatis.' : 'Tidak ada pesanan yang sedang diproses.') . '</div>';
    echo '</div></section>';
}
echo '</div>';

echo '<h2 class="sec-t">Riwayat terakhir</h2><div class="tw"><table><tr><th>ID</th><th>Waktu</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Petugas</th></tr>';
foreach ($history as $o)
    echo '<tr><td>#' . e($o['id_pesanan']) . '</td><td>' . e($o['waktu_pesanan']) . '</td><td>' . e($o['nama_pelanggan']) . '</td><td>' . rupiah($o['total_harga'])
       . '</td><td><span class="badge s-' . e($o['status_pesanan']) . '">' . e($o['status_pesanan']) . '</span></td><td>' . e($o['petugas'] ?? '-') . '</td></tr>';
if (!$history) echo '<tr><td colspan="6" class="muted">Belum ada riwayat pesanan.</td></tr>';
echo '</table></div>';
bottom();
