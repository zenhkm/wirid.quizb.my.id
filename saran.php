<?php
/*******************************
 * saran.php
 * - Form Saran (Nama, Email, Pesan)
 * - INSERT ke MySQL (mysqli + prepared statements)
 * - Siap di-iframe dari ppma.infinityfree.me
 *******************************/

// Izinkan form di-embed dari domain aplikasi yang digunakan.
header("Content-Security-Policy: frame-ancestors 'self' https://wirid.quizb.my.id https://quizb.my.id https://ppma.infinityfree.me http://ppma.infinityfree.me http://localhost:* http://127.0.0.1:*");

// Matikan error ke output user (biar rapi di iframe)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Kredensial DB
$DB_HOST = 'localhost';
$DB_NAME = 'quic1934_wirid';
$DB_USER = 'quic1934_zenhkm';
$DB_PASS = '03Maret1990';

// Status & pesan
$ok = isset($_GET['ok']) ? true : false;
$err = '';
$justSubmitted = false;

// Koneksi (gunakan exceptions biar rapi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
  // Jika koneksi gagal, kita tetap render HTML agar tampilan rapi di iframe
  $err = 'Gagal tersambung ke database. Silakan coba beberapa saat lagi.';
}

// Tangani submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($err)) {
  $nama  = trim($_POST['nama'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pesan = trim($_POST['pesan'] ?? '');

  if ($nama === '' || $pesan === '') {
    $err = 'Nama dan pesan wajib diisi.';
  } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Format email tidak valid.';
  } else {
    try {
      $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
      $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255); // batasi 255

      $stmt = $mysqli->prepare("INSERT INTO saran (nama, email, pesan, ip, ua) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param('sssss', $nama, $email, $pesan, $ip, $ua);
      $stmt->execute();
      $stmt->close();

      // Hindari resubmission saat refresh dengan PRG (Post/Redirect/Get)
      header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?ok=1');
      exit;
    } catch (Throwable $e) {
      $err = 'Maaf, terjadi gangguan saat menyimpan. Coba lagi nanti.';
    }
  }
}

// HTML mulai di bawah
?>
<!doctype html>
<html lang="id" dir="auto">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Form Saran</title>
  <meta name="theme-color" content="#0f172a" />
  <style>
    :root{
      --bg:#0f172a;
      --text:#e2e8f0;
      --muted:#94a3b8;
      --card:#111827;
      --line:#ffffff22;
      --accent:#22c55e;
      --accent-soft:#22c55e33;
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:radial-gradient(circle at top,#17315c 0%,transparent 28%),var(--bg);
      color:var(--text);
      font:16px/1.68 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Noto Sans",sans-serif;
    }

    .wrap{max-width:760px;margin:0 auto;padding:12px}
    .card{
      background:rgba(17,24,39,.96);
      border:1px solid var(--line);
      border-radius:18px;
      padding:16px;
      box-shadow:0 8px 24px #00000028;
    }

    h1{margin:0 0 10px;font-size:clamp(22px,5.4vw,30px);line-height:1.2}
    p.small{font-size:14px;color:var(--muted);margin:8px 0 12px}

    form.form{display:grid;gap:12px;margin-top:10px}
    .row{display:grid;gap:12px}
    @media(min-width:640px){ .row{grid-template-columns:1fr 1fr} }

    label{display:grid;gap:6px;font-size:13px;color:var(--muted)}
    .input, textarea{
      width:100%;
      background:var(--bg);
      border:1px solid var(--line);
      color:var(--text);
      border-radius:12px;
      padding:12px 13px;
      outline:none;
    }
    .input:focus, textarea:focus{ box-shadow:0 0 0 3px var(--accent-soft); border-color:var(--accent-soft); }
    textarea{ min-height:160px; resize:vertical; }

    .actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
    .btn{
      cursor:pointer;
      border:1px solid var(--line);
      background:#ffffff10;
      color:var(--text);
      padding:10px 14px;
      border-radius:12px;
      font-weight:700;
      min-height:44px;
    }
    .btn.primary{ border-color:var(--accent-soft); background:#16a34a33; }
    .btn:disabled{ opacity:.6; cursor:not-allowed }

    .alert{
      margin-bottom:12px;padding:10px 12px;border-radius:12px;border:1px solid var(--line);
      background:#ffffff08;
    }
    .alert.ok{ border-color:var(--accent-soft); background:#16a34a22; }
    .alert.err{ border-color:#ef444422; background:#ef444411; }

    .footer-note{margin-top:10px;text-align:center;font-size:12px;color:var(--muted)}
    .brand{color:var(--accent);font-weight:700}

    @media (max-width:560px){
      .wrap{padding:10px}
      .card{padding:14px}
      .actions .btn{width:100%}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Form Saran</h1>
      <p class="small">Sampaikan saran/usulan Anda untuk perbaikan aplikasi <span class="brand">Mafatihul Akhyar</span>.</p>

      <?php if ($ok): ?>
        <div class="alert ok">Terima kasih! Saran Anda sudah kami terima.</div>
      <?php elseif ($err): ?>
        <div class="alert err"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="">
        <div class="row">
          <div>
            <label for="nama">Nama <span class="brand">*</span></label>
            <input class="input" id="nama" name="nama" placeholder="Nama Anda" required />
          </div>
          <div>
            <label for="email">Email</label>
            <input class="input" id="email" name="email" type="email" placeholder="email@contoh.com" />
          </div>
        </div>
        <div>
          <label for="pesan">Pesan <span class="brand">*</span></label>
          <textarea class="input" id="pesan" name="pesan" placeholder="Tulis saran/usulan Anda..." required></textarea>
        </div>
        <div class="actions">
          <button type="reset" class="btn">Reset</button>
          <button type="submit" class="btn primary">Kirim</button>
        </div>
      </form>

      <p class="footer-note">Data tersimpan aman di server. Kami tidak membagikan informasi Anda kepada pihak lain.</p>
    </div>
  </div>
</body>
</html>
