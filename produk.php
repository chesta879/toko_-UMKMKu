<?php
// Staf: kelola produk toko (termasuk unggah gambar)
require 'config.php'; need(); csrf_check();
$tid = user()['id_toko'];
$dir = __DIR__ . '/uploads/';

function simpan_gambar(array $f, string $dir): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Unggah gambar gagal');
    if ($f['size'] > 2 * 1024 * 1024) throw new Exception('Ukuran gambar maksimal 2 MB');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new Exception('Gambar harus JPG, PNG, atau WEBP');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) throw new Exception('Gagal menyimpan gambar');
    return $name;
}
function hapus_file(?string $name, string $dir) { if ($name && is_file($dir . $name)) @unlink($dir . $name); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    try {
        if ($a === 'del') {
            $s = $pdo->prepare('SELECT gambar FROM produk WHERE id_produk=? AND id_toko=?'); $s->execute([$id, $tid]); $g = $s->fetchColumn();
            try { $pdo->prepare('DELETE FROM produk WHERE id_produk=? AND id_toko=?')->execute([$id, $tid]); }
            catch (PDOException $x) { throw new Exception('Produk sudah pernah dipesan dan tidak bisa dihapus. Ubah stoknya menjadi 0.'); }
            hapus_file($g ?: null, $dir);
            flash('Produk dihapus');
        } else {
            $nama = trim($_POST['nama_produk'] ?? ''); $kat = (int)($_POST['id_kategori'] ?? 0);
            $harga = (int)($_POST['harga'] ?? -1); $stok = (int)($_POST['stok'] ?? -1);
            if ($nama === '' || mb_strlen($nama) > 120 || $harga < 0 || $stok < 0) throw new Exception('Data produk tidak valid');
            $s = $pdo->prepare('SELECT 1 FROM kategori WHERE id_kategori=?'); $s->execute([$kat]);
            if (!$s->fetch()) throw new Exception('Kategori tidak valid');
            $baru = simpan_gambar($_FILES['gambar'] ?? [], $dir);
            if ($a === 'add') {
                $pdo->prepare('INSERT INTO produk(id_toko,id_kategori,nama_produk,gambar,harga,stok) VALUES(?,?,?,?,?,?)')->execute([$tid, $kat, $nama, $baru, $harga, $stok]);
                flash('Produk ditambahkan');
            } else {
                $s = $pdo->prepare('SELECT gambar FROM produk WHERE id_produk=? AND id_toko=?'); $s->execute([$id, $tid]); $lama = $s->fetchColumn();
                if ($lama === false) { hapus_file($baru, $dir); throw new Exception('Produk tidak ditemukan'); }
                $pdo->prepare('UPDATE produk SET id_kategori=?, nama_produk=?, harga=?, stok=?, gambar=? WHERE id_produk=? AND id_toko=?')
                    ->execute([$kat, $nama, $harga, $stok, $baru ?? $lama, $id, $tid]);
                if ($baru) hapus_file($lama ?: null, $dir);
                flash('Produk diperbarui');
            }
        }
    } catch (Exception $x) { flash('Gagal: ' . $x->getMessage()); }
    header('Location: produk.php'); exit;
}

$kats = $pdo->query('SELECT * FROM kategori ORDER BY nama_kategori')->fetchAll();
$ed = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM produk WHERE id_produk=? AND id_toko=?'); $s->execute([(int)$_GET['edit'], $tid]); $ed = $s->fetch() ?: null; }
$s = $pdo->prepare('SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id_kategori=p.id_kategori WHERE p.id_toko=? ORDER BY p.id_produk DESC LIMIT 200');
$s->execute([$tid]); $rows = $s->fetchAll();

top('Produk');
if (!$kats) echo '<div class="msg">Belum ada kategori. Admin perlu menambahkan kategori terlebih dahulu.</div>';
echo '<form method="post" enctype="multipart/form-data" class="row card">' . csrf_field()
   . '<input type="hidden" name="a" value="' . ($ed ? 'edit' : 'add') . '"><input type="hidden" name="id" value="' . e($ed['id_produk'] ?? '') . '">'
   . '<label>Nama produk<input name="nama_produk" maxlength="120" required value="' . e($ed['nama_produk'] ?? '') . '"></label><label>Kategori<select name="id_kategori" required>';
foreach ($kats as $k) echo '<option value="' . e($k['id_kategori']) . '"' . (($ed['id_kategori'] ?? 0) == $k['id_kategori'] ? ' selected' : '') . '>' . e($k['nama_kategori']) . '</option>';
echo '</select></label><label>Harga (Rp)<input type="number" name="harga" min="0" required value="' . e($ed['harga'] ?? '') . '"></label>'
   . '<label>Stok<input type="number" name="stok" min="0" required value="' . e($ed['stok'] ?? '') . '"></label>'
   . '<label>Gambar (JPG/PNG/WEBP, maks 2 MB)<input type="file" name="gambar" accept="image/jpeg,image/png,image/webp"></label>'
   . '<button>' . ($ed ? 'Simpan' : 'Tambah') . '</button>' . ($ed ? '<a class="btn b2" href="produk.php">Batal</a>' : '') . '</form>';

echo '<div class="tw"><table><tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . ($r['gambar'] ? '<img class="thumb" src="uploads/' . e($r['gambar']) . '" alt="">' : '—') . '</td><td>' . e($r['nama_produk']) . '</td><td>' . e($r['nama_kategori'])
       . '</td><td>' . rupiah($r['harga']) . '</td><td>' . e($r['stok']) . '</td><td><div class="acts"><a class="btn b2" href="?edit=' . e($r['id_produk']) . '">Ubah</a>'
       . '<form method="post" onsubmit="return confirm(\'Hapus produk ini?\')">' . csrf_field() . '<input type="hidden" name="a" value="del"><input type="hidden" name="id" value="' . e($r['id_produk']) . '"><button class="bd">Hapus</button></form></div></td></tr>';
}
if (!$rows) echo '<tr><td colspan="6" class="muted">Belum ada produk.</td></tr>';
echo '</table></div>';
bottom();
