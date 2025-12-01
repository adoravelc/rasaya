<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lupa Kata Sandi — RASAYA</title>
  @vite(['resources/js/app.js'])
</head>
<body class="auth-bg d-flex align-items-center" style="min-height:100vh;background:#f7f7f2">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="text-center mb-4">
          <div class="fw-bold" style="font-size:1.5rem;color:#192653;">RASAYA<span class="brand-dot" style="display:inline-block;width:.6rem;height:.6rem;border-radius:50%;background:#0F6A49;margin-left:.35rem"></span></div>
          <div class="text-muted small">Ajukan permohonan reset kata sandi</div>
        </div>
        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            @if(session('status'))
              <div class="alert alert-success mb-3">
                {{ session('status') }}<br>
                <small class="text-muted">Permintaan sudah diberikan kepada admin, admin akan menghubungi anda untuk memberikan token password baru.</small>
              </div>
            @endif
            <form method="POST" action="{{ route('password.forgot.request') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label">NIS/NUPTK (atau Email)</label>
                <input type="text" name="identifier" class="form-control" placeholder="Masukkan NIS/NUPTK atau email" required>
                <div class="form-text">Isi salah satu saja.</div>
              </div>
              @if (config('auth.reset_email_enabled'))
              <div class="mb-3">
                <label class="form-label">Pilih metode</label>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="method" id="method_admin" value="admin" checked>
                  <label class="form-check-label" for="method_admin">
                    Minta admin reset password
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="method" id="method_email" value="email">
                  <label class="form-check-label" for="method_email">
                    Kirim tautan reset ke email terdaftar
                  </label>
                </div>
                <div class="form-text">Jika email tidak ditemukan, permohonan tetap dicatat dan bisa diproses admin.</div>
              </div>
              @else
              <input type="hidden" name="method" value="admin">
              @endif
              <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit">Ajukan Atur Ulang Kata Sandi</button>
                <a class="btn btn-outline-secondary" href="{{ url('/login') }}">Kembali ke Masuk</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
