<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Atur Ulang Kata Sandi — RASAYA</title>
  @vite(['resources/js/app.js'])
</head>
<body class="auth-bg d-flex align-items-center" style="min-height:100vh;background:#f7f7f2">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <h1 class="h5 mb-3">Atur Ulang Kata Sandi</h1>
            @if ($errors->any())
              <div class="alert alert-danger py-2">
                {{ $errors->first() }}
              </div>
            @endif
            <form method="POST" action="{{ route('password.reset.submit') }}">
              @csrf
              <input type="hidden" name="token" value="{{ $token }}">
              <input type="hidden" name="email" value="{{ $email }}">
              <div class="mb-3">
                <label class="form-label">Kata sandi baru</label>
                <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimal 8 karakter">
              </div>
              <div class="mb-3">
                <label class="form-label">Ulangi kata sandi baru</label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
              </div>
              <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit">Simpan Kata Sandi</button>
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
