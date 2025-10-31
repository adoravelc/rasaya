@props(['role' => 'guest'])
@php
    $is = fn($name) => request()->routeIs($name) ? 'active' : '';
    $guruJenis = optional(auth()->user()->guru)->jenis; // 'bk' | 'wali_kelas' | null
@endphp

<aside class="col-12 col-md-3 col-lg-2 p-0 sidebar">
    {{-- Mobile header (only visible on small screens) --}}
    <div class="d-md-none p-3 border-bottom bg-white">
        <div class="small text-muted now-wita"></div>
        <div class="mt-2">Halo, <strong>{{ auth()->user()->name }}</strong></div>
        <form class="mt-2" method="POST" action="{{ route('logout') }}" onsubmit="return confirm('Yakin ingin logout?')">
            @csrf
            <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
        </form>
    </div>
    <div class="p-3">
        <div class="text-uppercase text-muted fw-semibold small mb-2">Menu</div>

        @switch($role)
            {{-- ================= ADMIN ================= --}}
            @case('admin')
                <nav class="nav nav-pills flex-column gap-1">
                    <a class="nav-link {{ $is('admin.dashboard*') }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    
                    <div class="text-uppercase text-muted fw-semibold small mb-1 mt-3">Analytics & Monitoring</div>
                    <a class="nav-link {{ $is('admin.dashboard.login-history') }}" href="{{ route('admin.dashboard.login-history') }}">
                        <i class="bi bi-clock-history"></i> Login History
                    </a>
                    <a class="nav-link {{ $is('admin.dashboard.audit-logs') }}" href="{{ route('admin.dashboard.audit-logs') }}">
                        <i class="bi bi-journal-text"></i> Audit Logs
                    </a>
                    
                    <div class="text-uppercase text-muted fw-semibold small mb-1 mt-3">Manajemen Data</div>
                    <a class="nav-link {{ $is('admin.kelas.index') }}" href="{{ route('admin.kelas.index') }}">
                        <i class="bi bi-door-closed"></i> Manajemen Kelas
                    </a>
                    <a class="nav-link {{ $is('admin.kategori.index') }}" href="{{ route('admin.kategori.index') }}">
                        <i class="bi bi-tags"></i> Manajemen Kategori
                    </a>
                    <a class="nav-link {{ $is('admin.guru.index') }}" href="{{ route('admin.guru.index') }}">
                        <i class="bi bi-person-badge"></i> Manajemen Guru
                    </a>
                    <a class="nav-link {{ $is('admin.siswa.index') }}" href="{{ route('admin.siswa.index') }}">
                        <i class="bi bi-people"></i> Manajemen Siswa
                    </a>
                    
                </nav>
            @break

            {{-- ================= GURU (BK & WALI KELAS) ================= --}}
            @case('guru')
                <nav class="nav nav-pills flex-column gap-1">
                    <a class="nav-link {{ $is('guru.bk.dashboard') }} {{ $is('guru.wk.dashboard') }}"
                        href="{{ url('/guru') }}">Dashboard</a>

                    {{-- Observasi / Input Guru --}}
                    <a class="nav-link {{ $is('guru.observasi.*') }}" href="{{ route('guru.observasi.index') }}">Input Guru
                        (Observasi)</a>

                    {{-- Analisis Input (untuk semua guru; wali kelas otomatis dibatasi siswanya sendiri) --}}
                    <a class="nav-link {{ $is('guru.analisis.*') }} {{ $is('guru.bk.analisis.*') }}" href="{{ route('guru.analisis.index') }}">Analisis Input</a>

                    {{-- Tren Emosi Siswa --}}
                    <a class="nav-link {{ $is('guru.tren_emosi.*') }}" href="{{ route('guru.tren_emosi.index') }}">Tren Emosi Siswa</a>

                    {{-- === BARU: Slot Konseling (hanya untuk Guru BK) === --}}
                    @if ($guruJenis === 'bk')
                        <a class="nav-link {{ $is('guru.bk.slots.view') }} {{ $is('guru.guru_bk.slots.*') }}"
                            href="{{ route('guru.guru_bk.slots.view') }}">Slot Konseling (BK)</a>
                    @endif
                    <a class="nav-link disabled">Laporan Siswa (segera)</a>
                </nav>
            @break

            {{-- ================= WALI KELAS (opsional bila role terpisah) ================= --}}
            @case('wali_kelas')
                <nav class="nav nav-pills flex-column gap-1">
                    <a class="nav-link" href="{{ url('/guru/wk') }}">Dashboard</a>
                    <a class="nav-link {{ $is('guru.observasi.*') }}" href="{{ route('guru.observasi.index') }}">Input Guru
                        (Observasi)</a>
                </nav>
            @break

            @default
                <nav class="nav nav-pills flex-column gap-1">
                    <a class="nav-link" href="{{ url('/') }}">Home</a>
                </nav>
        @endswitch
    </div>
</aside>
