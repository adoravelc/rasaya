<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RASAYA</title>
    <link rel="icon" type="image/png" href="{{ asset('images/app_icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app_icon.png') }}">
    @vite(['resources/js/app.js'])
    <style>
        .home-bg {
            min-height: 100vh;
            background: var(--ras-broken-white, #f7f7f2);
        }
    </style>
</head>
<body class="home-bg d-flex align-items-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-9 col-lg-7 col-xl-6">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/logo_horizontal.png') }}" alt="RASAYA" style="max-height:56px;width:auto;object-fit:contain;">
                    <p class="text-muted mt-2 mb-0">Platform refleksi, observasi, dan pendampingan siswa</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="mb-3">Masuk ke RASAYA</h5>
                        <p class="text-muted mb-4">Pilih mode akses yang ingin digunakan.</p>

                        <div class="d-grid gap-2 mb-3">
                            @if (!config('auth.guest_only_mode', false))
                                <a href="{{ route('login') }}" class="btn btn-primary">Masuk Akun Utama</a>
                            @endif
                            <a href="{{ route('guest.exit') }}" class="btn btn-outline-danger">Reset Sesi Browser</a>
                        </div>

                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-2">Mode Guest (Read-Only)</div>
                            <p class="small text-muted mb-3">Untuk demo sistem. Semua aksi ubah data akan ditolak dan tidak disimpan ke database.</p>

                            <div class="d-grid gap-2">
                                <form method="POST" action="{{ route('guest.enter', ['role' => 'guru-bk']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary w-100">Masuk sebagai Guest Guru BK</button>
                                </form>

                                <form method="POST" action="{{ route('guest.enter', ['role' => 'siswa']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary w-100">Masuk sebagai Guest Siswa</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center text-muted small mt-3">
                    © {{ date('Y') }} RASAYA
                </div>
            </div>
        </div>
    </div>
</body>
</html>
