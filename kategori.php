<?php
// Admin: kelola kategori produk
require 'config.php'; need('admin'); csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0); $nama = trim($_POST['nama_kategori'] ?? '');
    try {
        if ($a === 'del') {
            try { $pdo->prepare('DELETE FROM kategori WHERE id_kategori=?')->execute([$id]); flash('Kategori dihapus'); }
            catch (PDOException $x) { throw new Exception('Kategori masih dipakai oleh produk'); }
        } else {
            if ($nama === '' || mb_strlen($nama) > 60) throw new Exception('Nama kategori 1-60 karakter');
            try {
                if ($a === 'add') $pdo->prepare('INSERT INTO kategori(nama_kategori) VALUES(?)')->execute([$nama]);
                else $pdo->prepare('UPDATE kategori SET nama_kategori=? WHERE id_kategori=?')->execute([$nama, $id]);
            } catch (PDOException $x) { throw new Exception('Nama kategori sudah ada'); }
            flash($a === 'add' ? 'Kategori ditambahkan' : 'Kategori diperbarui');
        }
    } catch (Exception $x) { flash('Gagal: ' . $x->getMessage()); }
    header('Location: kategori.php'); exit;
}

$ed = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM kategori WHERE id_kategori=?'); $s->execute([(int)$_GET['edit']]); $ed = $s->fetch() ?: null; }
$rows = $pdo->query('SELECT k.*, (SELECT COUNT(*) FROM produk p WHERE p.id_kategori=k.id_kategori) AS jml FROM kategori k ORDER BY nama_kategori')->fetchAll();

top('Kategori');
echo '<form method="post" class="row card">' . csrf_field() . '<input type="hidden" name="a" value="' . ($ed ? 'edit' : 'add') . '"><input type="hidden" name="id" value="' . e($ed['id_kategori'] ?? '') . '">'
   . '<label>Nama kategori<input name="nama_kategori" maxlength="60" required value="' . e($ed['nama_kategori'] ?? '') . '"></label><button>' . ($ed ? 'Simpan' : 'Tambah') . '</button>'
   . ($ed ? '<a class="btn b2" href="kategori.php">Batal</a>' : '') . '</form>';
echo '<div class="tw"><table><tr><th>ID</th><th>Nama</th><th>Jumlah produk</th><th>Aksi</th></tr>';
foreach ($rows as $r)
    echo '<tr><td>' . e($r['id_kategori']) . '</td><td>' . e($r['nama_kategori']) . '</td><td>' . e($r['jml']) . '</td><td><div class="acts"><a class="btn b2" href="?edit=' . e($r['id_kategori']) . '">Ubah</a>'
       . '<form method="post" onsubmit="return confirm(\'Hapus kategori ini?\')">' . csrf_field() . '<input type="hidden" name="a" value="del"><input type="hidden" name="id" value="' . e($r['id_kategori']) . '"><button class="bd">Hapus</button></form></div></td></tr>';
echo '</table></div>';
bottom();
