<?php
// Admin: kelola pengguna (admin/kasir) pada toko sendiri
require 'config.php'; need('admin'); csrf_check();
$tid = user()['id_toko']; $me = user()['id_pengguna'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    try {
        if ($a === 'del') {
            if ($id === $me) throw new Exception('Tidak dapat menghapus akun sendiri');
            $pdo->prepare('DELETE FROM pengguna WHERE id_pengguna=? AND id_toko=?')->execute([$id, $tid]);
            flash('Pengguna dihapus');
        } else {
            $nama = trim($_POST['nama'] ?? ''); $un = trim($_POST['username'] ?? ''); $pw = $_POST['password'] ?? ''; $role = $_POST['hak_akses'] ?? '';
            if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) throw new Exception('Nama 2-100 karakter');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $un)) throw new Exception('Username 3-40 karakter (huruf, angka, _ . -)');
            if (!in_array($role, ['admin', 'kasir'], true)) throw new Exception('Hak akses tidak valid');
            if ($id === $me && $role !== 'admin') throw new Exception('Anda tidak dapat menurunkan hak akses akun sendiri');
            if ($a === 'add' || $pw !== '') { if (strlen($pw) < 8) throw new Exception('Password minimal 8 karakter'); }
            try {
                if ($a === 'add') {
                    $pdo->prepare('INSERT INTO pengguna(id_toko,nama,username,password,hak_akses) VALUES(?,?,?,?,?)')->execute([$tid, $nama, $un, password_hash($pw, PASSWORD_DEFAULT), $role]);
                } else {
                    $set = 'nama=?, username=?, hak_akses=?'; $v = [$nama, $un, $role];
                    if ($pw !== '') { $set .= ', password=?'; $v[] = password_hash($pw, PASSWORD_DEFAULT); }
                    $v[] = $id; $v[] = $tid;
                    $pdo->prepare("UPDATE pengguna SET $set WHERE id_pengguna=? AND id_toko=?")->execute($v);
                }
            } catch (PDOException $x) { throw new Exception('Username sudah dipakai'); }
            flash($a === 'add' ? 'Pengguna ditambahkan' : 'Pengguna diperbarui');
        }
    } catch (Exception $x) { flash('Gagal: ' . $x->getMessage()); }
    header('Location: pengguna.php'); exit;
}

$ed = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM pengguna WHERE id_pengguna=? AND id_toko=?'); $s->execute([(int)$_GET['edit'], $tid]); $ed = $s->fetch() ?: null; }
$s = $pdo->prepare('SELECT id_pengguna, nama, username, hak_akses FROM pengguna WHERE id_toko=? ORDER BY id_pengguna'); $s->execute([$tid]); $rows = $s->fetchAll();

top('Pengguna');
echo '<form method="post" class="row card" autocomplete="off">' . csrf_field() . '<input type="hidden" name="a" value="' . ($ed ? 'edit' : 'add') . '"><input type="hidden" name="id" value="' . e($ed['id_pengguna'] ?? '') . '">'
   . '<label>Nama<input name="nama" maxlength="100" required value="' . e($ed['nama'] ?? '') . '"></label>'
   . '<label>Username<input name="username" maxlength="40" required value="' . e($ed['username'] ?? '') . '"></label>'
   . '<label>Password<input type="password" name="password" minlength="8" autocomplete="new-password"' . ($ed ? ' placeholder="(kosongkan jika tidak diubah)"' : ' required') . '></label>'
   . '<label>Hak akses<select name="hak_akses">';
foreach (['kasir', 'admin'] as $r) echo '<option' . (($ed['hak_akses'] ?? 'kasir') === $r ? ' selected' : '') . '>' . $r . '</option>';
echo '</select></label><button>' . ($ed ? 'Simpan' : 'Tambah') . '</button>' . ($ed ? '<a class="btn b2" href="pengguna.php">Batal</a>' : '') . '</form>';

echo '<div class="tw"><table><tr><th>ID</th><th>Nama</th><th>Username</th><th>Hak akses</th><th>Aksi</th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . e($r['id_pengguna']) . '</td><td>' . e($r['nama']) . '</td><td>' . e($r['username']) . '</td><td>' . e($r['hak_akses']) . '</td><td><div class="acts"><a class="btn b2" href="?edit=' . e($r['id_pengguna']) . '">Ubah</a>';
    if ((int)$r['id_pengguna'] !== $me)
        echo '<form method="post" onsubmit="return confirm(\'Hapus pengguna ini?\')">' . csrf_field() . '<input type="hidden" name="a" value="del"><input type="hidden" name="id" value="' . e($r['id_pengguna']) . '"><button class="bd">Hapus</button></form>';
    echo '</div></td></tr>';
}
echo '</table></div>';
bottom();
