<?php
// Admin: pengaturan toko (alamat, kontak, jam operasional, status buka)
require 'config.php'; need('admin'); csrf_check();
$tid = user()['id_toko'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nama = trim($_POST['nama_toko'] ?? ''); $alamat = trim($_POST['alamat'] ?? ''); $kontak = trim($_POST['kontak'] ?? '');
        $buka = $_POST['jam_buka'] ?? ''; $tutup = $_POST['jam_tutup'] ?? ''; $st = $_POST['status_buka'] ?? '';
        $jam = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if ($nama === '' || $alamat === '' || $kontak === '' || mb_strlen($nama) > 100 || mb_strlen($alamat) > 255 || mb_strlen($kontak) > 30) throw new Exception('Data toko tidak valid');
        if (!preg_match($jam, $buka) || !preg_match($jam, $tutup)) throw new Exception('Format jam tidak valid');
        if (!in_array($st, ['buka', 'tutup'], true)) throw new Exception('Status tidak valid');
        $pdo->prepare('UPDATE toko SET nama_toko=?, alamat=?, kontak=?, jam_buka=?, jam_tutup=?, status_buka=? WHERE id_toko=?')->execute([$nama, $alamat, $kontak, $buka, $tutup, $st, $tid]);
        flash('Pengaturan toko disimpan');
    } catch (Exception $x) { flash('Gagal: ' . $x->getMessage()); }
    header('Location: toko.php'); exit;
}

$s = $pdo->prepare('SELECT * FROM toko WHERE id_toko=?'); $s->execute([$tid]); $t = $s->fetch();
top('Pengaturan toko');
echo '<form method="post" class="row card">' . csrf_field()
   . '<label>Nama toko<input name="nama_toko" maxlength="100" required value="' . e($t['nama_toko']) . '"></label>'
   . '<label>Alamat<input name="alamat" maxlength="255" required style="min-width:280px" value="' . e($t['alamat']) . '"></label>'
   . '<label>Kontak<input name="kontak" maxlength="30" required value="' . e($t['kontak']) . '"></label>'
   . '<label>Jam buka<input type="time" name="jam_buka" required value="' . e(substr($t['jam_buka'], 0, 5)) . '"></label>'
   . '<label>Jam tutup<input type="time" name="jam_tutup" required value="' . e(substr($t['jam_tutup'], 0, 5)) . '"></label>'
   . '<label>Status<select name="status_buka"><option value="buka"' . ($t['status_buka'] === 'buka' ? ' selected' : '') . '>buka</option><option value="tutup"' . ($t['status_buka'] === 'tutup' ? ' selected' : '') . '>tutup</option></select></label>'
   . '<button>Simpan</button></form>'
   . '<p class="muted">Pelanggan hanya bisa memesan jika status "buka" dan jam sekarang berada di antara jam buka dan jam tutup.</p>';
bottom();
