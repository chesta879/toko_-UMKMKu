<?php
// Halaman pelanggan: lihat produk per kategori, keranjang, dan buat pesanan
require 'config.php';
csrf_check();

$tid = (int)($_GET['toko'] ?? 0);
if ($tid) { $s = $pdo->prepare('SELECT * FROM toko WHERE id_toko=?'); $s->execute([$tid]); }
else $s = $pdo->query('SELECT * FROM toko ORDER BY id_toko LIMIT 1');
$toko = $s->fetch();
if (!$toko) {
    top(APP_NAME, 'public');
    echo '<div class="empty" style="margin-top:40px">Belum ada toko. Import <code>database.sql</code> terlebih dahulu.</div>';
    bottom(); exit;
}
$tid = (int)$toko['id_toko'];
$buka = toko_buka($toko);
$cart = $_SESSION['cart'][$tid] ?? [];
$url = 'index.php?toko=' . $tid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';
    try {
        if ($a === 'add' || $a === 'inc' || $a === 'dec') {
            $id = (int)($_POST['id'] ?? 0);
            $s = $pdo->prepare('SELECT nama_produk, stok FROM produk WHERE id_produk=? AND id_toko=?');
            $s->execute([$id, $tid]); $p = $s->fetch();
            if (!$p) throw new Exception('Produk tidak ditemukan');
            $now = $cart[$id] ?? 0;
            $new = $a === 'dec' ? $now - 1 : $now + 1;
            if ($new > $p['stok']) throw new Exception('Stok ' . $p['nama_produk'] . ' hanya ' . $p['stok']);
            if ($new <= 0) unset($_SESSION['cart'][$tid][$id]); else $_SESSION['cart'][$tid][$id] = $new;
            if ($a === 'add') flash($p['nama_produk'] . ' masuk keranjang');
        } elseif ($a === 'checkout') {
            if (!$buka) throw new Exception('Toko sedang tutup');
            $nama = trim($_POST['nama'] ?? '');
            if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) throw new Exception('Nama pelanggan harus 2-100 karakter');
            if (!$cart) throw new Exception('Keranjang masih kosong');

            $pdo->beginTransaction();
            $lines = []; $total = 0;
            foreach ($cart as $id => $q) {
                $s = $pdo->prepare('SELECT id_produk, nama_produk, harga, stok FROM produk WHERE id_produk=? AND id_toko=? FOR UPDATE');
                $s->execute([$id, $tid]); $p = $s->fetch();
                if (!$p || $p['stok'] < $q) throw new Exception('Stok ' . ($p['nama_produk'] ?? 'produk') . ' tidak mencukupi');
                $sub = $p['harga'] * $q; $total += $sub;
                $lines[] = [$id, $q, $sub];
            }
            $pdo->prepare('INSERT INTO pesanan(id_toko,nama_pelanggan,total_harga) VALUES(?,?,?)')->execute([$tid, $nama, $total]);
            $idp = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO detail_pesanan(id_pesanan,id_produk,jumlah,subtotal) VALUES(?,?,?,?)');
            $upd = $pdo->prepare('UPDATE produk SET stok=stok-? WHERE id_produk=?');
            foreach ($lines as [$id, $q, $sub]) { $ins->execute([$idp, $id, $q, $sub]); $upd->execute([$q, $id]); }
            $pdo->commit();
            unset($_SESSION['cart'][$tid]);
            $_SESSION['done'] = ['id' => $idp, 'nama' => $nama, 'total' => $total];
        }
    } catch (Exception $x) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Gagal: ' . $x->getMessage());
    }
    header('Location: ' . $url); exit;
}

$done = $_SESSION['done'] ?? null; unset($_SESSION['done']);

// Produk per kategori
$rows = $pdo->prepare('SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id_kategori=p.id_kategori WHERE p.id_toko=? ORDER BY k.nama_kategori, p.nama_produk');
$rows->execute([$tid]);
$byCat = [];
foreach ($rows as $r) $byCat[$r['nama_kategori']][] = $r;

// Isi keranjang (harga selalu dari database)
$items = []; $total = 0; $count = 0;
if ($cart) {
    $in = implode(',', array_fill(0, count($cart), '?'));
    $s = $pdo->prepare("SELECT id_produk, nama_produk, harga FROM produk WHERE id_toko=? AND id_produk IN ($in)");
    $s->execute(array_merge([$tid], array_keys($cart)));
    foreach ($s as $p) {
        $q = $cart[$p['id_produk']]; $p['qty'] = $q; $p['sub'] = $q * $p['harga'];
        $total += $p['sub']; $count += $q; $items[] = $p;
    }
}

$wa = wa_nomor($toko['kontak']);
top($toko['nama_toko'] . ' · Pesan online', 'public', $toko);

// ----- Hero -----
echo '<section class="hero"><span class="st' . ($buka ? '' : ' off') . '"><i></i>' . ($buka ? 'Buka sekarang' : 'Sedang tutup') . '</span>'
   . '<h1>' . e($toko['nama_toko']) . '</h1><p>Pilih produk, kirim pesanan, dan ambil di toko.</p><div class="meta">'
   . '<span>' . icon('clock', 16) . 'Buka ' . e(substr($toko['jam_buka'], 0, 5)) . ' – ' . e(substr($toko['jam_tutup'], 0, 5)) . '</span>'
   . '<span>' . icon('pin', 16) . e($toko['alamat']) . '</span>'
   . '<span>' . icon('phone', 16) . e($toko['kontak']) . '</span></div>';
if ($wa) echo '<a class="btn sun" target="_blank" rel="noopener" href="https://wa.me/' . e($wa) . '">' . icon('chat', 18) . 'Chat WhatsApp</a>';
echo '</section>';

// ----- Pesanan berhasil -----
if ($done) {
    $pesan = rawurlencode("Halo {$toko['nama_toko']}, saya {$done['nama']}. Pesanan #{$done['id']} sudah saya buat, total " . rupiah($done['total']) . '.');
    echo '<div class="done" role="status"><span class="ck">' . icon('check', 24) . '</span><div><h2>Pesanan #' . e($done['id']) . ' diterima</h2>'
       . '<p>Atas nama <b>' . e($done['nama']) . '</b> · total <b>' . rupiah($done['total']) . '</b>. Sebutkan nomor pesanan ini ke kasir saat mengambil.</p>'
       . ($wa ? '<a class="btn" target="_blank" rel="noopener" href="https://wa.me/' . e($wa) . '?text=' . $pesan . '">' . icon('chat', 18) . 'Konfirmasi lewat WhatsApp</a>' : '')
       . '</div></div>';
}

echo '<div class="store" style="margin-top:26px"><section aria-label="Daftar produk">';

// ----- Pencarian & kategori -----
if ($byCat) {
    echo '<div class="tools"><label class="sf" for="q"><span class="muted" style="position:absolute;left:-9999px">Cari produk</span>' . icon('search', 18)
       . '<input id="q" type="search" placeholder="Cari produk…" autocomplete="off"></label><div class="chips" role="group" aria-label="Kategori"><button type="button" class="chip on" data-cat="">Semua</button>';
    foreach (array_keys($byCat) as $c) echo '<button type="button" class="chip" data-cat="' . e($c) . '">' . e($c) . '</button>';
    echo '</div></div>';
}

// ----- Produk -----
foreach ($byCat as $cat => $list) {
    echo '<div class="cat-sec"><h2>' . e($cat) . '</h2><div class="grid">';
    foreach ($list as $p) {
        $h = ($p['id_produk'] * 47) % 360;
        echo '<article class="pc" data-cat="' . e($cat) . '" data-name="' . e(mb_strtolower($p['nama_produk'])) . '"><div class="im" style="--h:' . $h . '">'
           . ($p['gambar'] ? '<img src="uploads/' . e($p['gambar']) . '" alt="" loading="lazy">' : e(mb_strtoupper(mb_substr($p['nama_produk'], 0, 1))))
           . ($p['stok'] < 1 ? '<span class="so">Habis</span>' : '') . '</div><div class="pb"><span class="nm">' . e($p['nama_produk']) . '</span><span class="kt">' . e($cat) . '</span>'
           . '<div class="ft"><div><span class="price">' . rupiah($p['harga']) . '</span>'
           . ($p['stok'] > 0 && $p['stok'] <= 5 ? '<small class="low">Sisa ' . e($p['stok']) . '</small>' : '') . '</div>';
        if ($p['stok'] > 0)
            echo '<form class="ajax" method="post" action="' . e($url) . '">' . csrf_field() . '<input type="hidden" name="a" value="add"><input type="hidden" name="id" value="' . e($p['id_produk']) . '">'
               . '<button class="add" aria-label="Tambah ' . e($p['nama_produk']) . ' ke keranjang">' . icon('plus', 18) . '</button></form>';
        echo '</div></div></article>';
    }
    echo '</div></div>';
}
if (!$byCat) echo '<div class="empty">Belum ada produk yang dijual.</div>';
echo '<div id="nomatch" class="empty" hidden>Produk tidak ditemukan. Coba kata kunci atau kategori lain.</div></section>';

// ----- Keranjang -----
$step = function ($a, $id, $ic, $label) use ($url) {
    return '<form class="ajax" method="post" action="' . e($url) . '">' . csrf_field() . '<input type="hidden" name="a" value="' . $a . '"><input type="hidden" name="id" value="' . e($id) . '">'
         . '<button aria-label="' . $label . '">' . icon($ic, 14) . '</button></form>';
};
echo '<div id="cartwrap"><div class="backdrop" data-cart-toggle></div>';
if ($items) echo '<button type="button" class="cartbar" data-cart-toggle><span>' . e($count) . ' item</span><b>' . rupiah($total) . '</b><span>Lihat keranjang</span></button>';
echo '<aside class="cartpanel" aria-label="Keranjang"><div class="ch"><h2>Keranjang</h2><button type="button" class="cx" data-cart-toggle aria-label="Tutup keranjang">' . icon('x', 16) . '</button></div>';
if (!$items) {
    echo '<div class="ce">' . icon('cart', 36) . '<div>Keranjang masih kosong.<br>Tekan tombol + pada produk untuk mulai memesan.</div></div>';
} else {
    foreach ($items as $i) {
        echo '<div class="li"><div class="lt"><b>' . e($i['nama_produk']) . '</b><small>' . rupiah($i['harga']) . ' / item</small></div>'
           . '<div class="qs">' . $step('dec', $i['id_produk'], 'minus', 'Kurangi ' . e($i['nama_produk'])) . '<span>' . e($i['qty']) . '</span>' . $step('inc', $i['id_produk'], 'plus', 'Tambah ' . e($i['nama_produk'])) . '</div>'
           . '<div class="ls"><span></span><span class="price">' . rupiah($i['sub']) . '</span></div></div>';
    }
    echo '<div class="tot"><span>Total</span><b>' . rupiah($total) . '</b></div>'
       . '<form class="co" method="post" action="' . e($url) . '">' . csrf_field() . '<input type="hidden" name="a" value="checkout">'
       . '<label>Nama pemesan<input name="nama" minlength="2" maxlength="100" autocomplete="name" placeholder="Contoh: Budi" required></label>'
       . '<button class="wide"' . ($buka ? '' : ' disabled') . '>' . icon('check', 18) . 'Kirim pesanan</button>'
       . ($buka ? '' : '<span class="muted">Toko sedang tutup'
            . (($toko['status_buka'] ?? '') !== 'buka' ? ' (status: tutup).' : ' di luar jam buka ' . e(substr($toko['jam_buka'], 0, 5)) . '–' . e(substr($toko['jam_tutup'], 0, 5)) . '.')
            . ' Pesanan belum bisa dikirim.</span>') . '</form>';
}
echo '</aside></div></div>';
bottom();
