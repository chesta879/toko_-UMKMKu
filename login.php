<?php
require 'config.php';
csrf_check();
if (user()) { header('Location: pesanan.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wait = ($_SESSION['login_until'] ?? 0) - time();
    if ($wait > 0) {
        $err = "Terlalu banyak percobaan. Coba lagi dalam $wait detik.";
    } else {
        $s = $pdo->prepare('SELECT * FROM pengguna WHERE username=? LIMIT 1');
        $s->execute([trim($_POST['username'] ?? '')]);
        $u = $s->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_fail'], $_SESSION['login_until']);
            $_SESSION['u'] = ['id_pengguna' => (int)$u['id_pengguna'], 'id_toko' => (int)$u['id_toko'], 'nama' => $u['nama'], 'hak_akses' => $u['hak_akses']];
            header('Location: pesanan.php'); exit;
        }
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) { $_SESSION['login_until'] = time() + 60; $_SESSION['login_fail'] = 0; }
        $err = 'Username atau password salah';
    }
}
top('Masuk staf · ' . APP_NAME, 'auth');
?>
<main class="auth">
  <section class="brandp">
    <a class="logo" href="index.php" style="padding:0"><span class="mk"><?= icon('store', 20) ?></span><span><b><?= e(APP_NAME) ?></b></span></a>
    <div>
      <h2>Pesanan toko beres dalam satu layar.</h2>
      <ul>
        <li><?= icon('check', 18) ?>Terima dan proses pesanan pelanggan</li>
        <li><?= icon('check', 18) ?>Stok berkurang otomatis saat dipesan</li>
        <li><?= icon('check', 18) ?>Kelola produk, kategori, dan jam buka</li>
      </ul>
    </div>
    <span class="muted" style="color:#9cc3d3">Khusus admin dan kasir toko.</span>
  </section>
  <section class="fp">
    <form method="post">
      <?= csrf_field() ?>
      <div><h1>Masuk</h1><p class="muted" style="margin:6px 0 0;font-size:14px">Gunakan akun yang diberikan admin toko.</p></div>
      <?php if ($err): ?><div class="msg" role="alert" style="margin:0"><?= e($err) ?></div><?php endif; ?>
      <label>Username<input name="username" autocomplete="username" autofocus required></label>
      <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
      <button class="wide">Masuk</button>
      <a href="index.php" class="muted" style="text-align:center">Kembali ke halaman toko</a>
    </form>
  </section>
</main>
<?php bottom();
