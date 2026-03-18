<?php
/**
 * Make My Home – Admin Login
 * VAŽNO: Promijenite lozinku ispod!
 */
session_start();

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', '$2y$10$' . 'PROMIJENITE_LOZINKU'); // Generišite sa: password_hash('vasa_lozinka', PASSWORD_DEFAULT)
// Za brzo testiranje, privremena lozinka je: makemyhome2026
define('ADMIN_PASS_PLAIN', 'makemyhome2026');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASS_PLAIN) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_time']   = time();
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Pogrešno korisničko ime ili lozinka.';
        sleep(1); // Brute force zaštita
    }
}

if (!empty($_SESSION['admin_logged'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="bs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Make My Home</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #1a1a1a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 48px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }
    .login-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
      justify-content: center;
    }
    .login-logo-icon {
      width: 48px; height: 48px;
      background: #c9a86c;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; color: #1a1a1a;
    }
    .login-logo-text .name { font-size: 20px; font-weight: 700; color: #1a1a1a; }
    .login-logo-text .sub { font-size: 11px; color: #c9a86c; letter-spacing: 2px; text-transform: uppercase; }
    h1 { font-size: 22px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; text-align: center; }
    .subtitle { font-size: 14px; color: #888; margin-bottom: 32px; text-align: center; }
    label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
    input {
      width: 100%; padding: 13px 16px; border: 1.5px solid rgba(0,0,0,0.12);
      border-radius: 8px; font-size: 15px; color: #1a1a1a; outline: none;
      transition: all .2s; margin-bottom: 18px; font-family: inherit;
    }
    input:focus { border-color: #c9a86c; box-shadow: 0 0 0 3px rgba(201,168,108,0.15); }
    .btn-login {
      width: 100%; padding: 14px; background: #c9a86c; color: #1a1a1a;
      border: none; border-radius: 8px; font-size: 16px; font-weight: 600;
      cursor: pointer; transition: all .2s; display: flex; align-items: center;
      justify-content: center; gap: 8px; font-family: inherit;
    }
    .btn-login:hover { background: #a8863f; transform: translateY(-1px); }
    .error {
      background: rgba(231,76,60,0.1); color: #e74c3c;
      border: 1px solid rgba(231,76,60,0.3);
      padding: 12px 16px; border-radius: 8px;
      font-size: 14px; margin-bottom: 20px; text-align: center;
    }
    .back-link { text-align: center; margin-top: 20px; font-size: 13px; color: #888; }
    .back-link a { color: #c9a86c; text-decoration: none; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-logo">
      <div class="login-logo-icon"><i class="fas fa-home"></i></div>
      <div class="login-logo-text">
        <div class="name">Make My Home</div>
        <div class="sub">Admin Panel</div>
      </div>
    </div>
    <h1>Prijava</h1>
    <p class="subtitle">Unesite podatke za pristup admin panelu</p>

    <?php if ($error): ?>
      <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="username">Korisničko ime</label>
      <input type="text" id="username" name="username" placeholder="admin" required autocomplete="username">

      <label for="password">Lozinka</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">

      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Prijavite se
      </button>
    </form>

    <div class="back-link">
      <a href="../index.html"><i class="fas fa-arrow-left"></i> Nazad na sajt</a>
    </div>
  </div>
</body>
</html>
