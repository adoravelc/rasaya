<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Permohonan Diterima — RASAYA</title>
  @vite(['resources/js/app.js'])
</head>
<body class="auth-bg d-flex align-items-center" style="min-height:100vh;background:#f7f7f2">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4 text-center">
            <h1 class="h5 mb-3">Permohonan reset password diterima</h1>
            <p class="text-muted">Permintaanmu sudah diteruskan ke admin. Admin akan memverifikasi dan menghubungi kamu untuk memberikan token password baru. Jika dalam beberapa jam belum ada kabar, silakan hubungi admin sekolah.</p>
            <div class="d-grid gap-2 mt-3">
              <a class="btn btn-primary" href="{{ url('/login') }}">Kembali ke Masuk</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
