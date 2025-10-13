{{-- resources/views/dashboards/admin.blade.php --}}
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin — RASAYA</title>
    @vite(['resources/js/app.js'])
    <style>
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #e5e7eb;
            background: #f8f9fa;
        }

        .sidebar .nav-link.active {
            background: #e9ecef;
            font-weight: 600;
        }

        .brand {
            font-weight: 700;
            letter-spacing: .3px;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand brand" href="{{ route('admin.dashboard') }}">RASAYA Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topbar"
                aria-controls="topbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="topbar">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item me-3 text-muted small">
                        Halo, <strong>{{ auth()->user()->name }}</strong>
                        <span class="d-none d-sm-inline">({{ auth()->user()->identifier }})</span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}"
                            onsubmit="return confirm('Yakin ingin logout?')">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            {{-- Sidebar --}}
            <aside class="col-12 col-md-3 col-lg-2 p-0 sidebar">
                <div class="p-3">
                    <div class="text-uppercase text-muted fw-semibold small mb-2">Menu</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                            href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>

                        <a class="nav-link {{ request()->routeIs('admin.kelas.*') ? 'active' : '' }}"
                            href="{{ route('admin.kelas.index') }}">📚 Manajemen Kelas</a>
                            <a class="nav-link" href="{{ route('admin.kategori.index') }}">🗂️ Manajemen Kategori</a>

                        {{-- contoh menu lain (disabled dulu) --}}
                        <a class="nav-link disabled">👩‍🏫 Data Guru (segera)</a>
                        <a class="nav-link disabled">🧑‍🎓 Data Siswa (segera)</a>
                    </nav>

                    <hr>
                    <div class="text-uppercase text-muted fw-semibold small mb-2">Lainnya</div>
                    <nav class="nav flex-column gap-1">
                        <a class="nav-link disabled">⚙️ Pengaturan</a>
                    </nav>
                </div>
            </aside>

            {{-- Konten --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                @if (session('ok'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('ok') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="mb-1">Dashboard</h3>
                        <div class="text-muted">Selamat datang kembali 👋</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Ringkasan Cepat</h5>
                                <p class="text-muted small mb-3">
                                    Akses cepat menuju modul yang sering dipakai.
                                </p>
                                <a href="{{ route('admin.kelas.index') }}" class="btn btn-primary">
                                    📚 Kelola Kelas
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- kartu kosong untuk ekspansi --}}
                    <div class="col-12 col-lg-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Aktivitas Terakhir</h5>
                                <p class="text-muted small mb-0">Belum ada data.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

</body>

</html>
