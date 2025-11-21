@props(['role' => 'guest'])
@php
    $is = fn($name) => request()->routeIs($name) ? 'active' : '';
    $guruJenis = optional(auth()->user()->guru)->jenis; // 'bk' | 'wali_kelas' | null
@endphp

<aside class="col-12 col-md-3 col-lg-2 p-0 sidebar">
    <div class="p-4">
        {{-- Logo RASAYA - Centered and Large --}}
        <div class="text-center mb-4 pb-4 border-bottom" style="border-color: rgba(148, 163, 184, 0.2) !important;">
            @if($role === 'admin')
                <div class="display-6 fw-bold mb-2" style="color: #1e3a8a; letter-spacing: 2px;">
                    RASAYA
                </div>
                <small class="d-block" style="color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Admin Portal</small>
            @else
                <div class="display-6 fw-bold mb-2" style="color: #ec4899; letter-spacing: 2px;">
                    RASAYA
                </div>
                <small class="d-block" style="color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Guru Portal</small>
            @endif
        </div>

        <div class="text-uppercase fw-semibold small mb-2" style="opacity: 0.7;">Menu</div>

        @switch($role)
            {{-- ================= ADMIN ================= --}}
            @case('admin')
                <nav class="nav nav-pills flex-column gap-1">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') && !request()->routeIs('admin.dashboard.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    
                    <div class="text-uppercase text-muted fw-semibold small mb-1 mt-3">Analytics & Monitoring</div>
                    <a class="nav-link {{ $is('admin.dashboard.login-history') }}" href="{{ route('admin.dashboard.login-history') }}">
                        <i class="bi bi-clock-history"></i> Login History
                    </a>
                    <a class="nav-link {{ $is('admin.dashboard.refleksi-history') }}" href="{{ route('admin.dashboard.refleksi-history') }}">
                        <i class="bi bi-journal-check"></i> History Refleksi
                    </a>
                    <a class="nav-link {{ $is('admin.dashboard.mood-history') }}" href="{{ route('admin.dashboard.mood-history') }}">
                        <i class="bi bi-emoji-smile"></i> History Mood
                    </a>
                    <a class="nav-link {{ $is('admin.dashboard.audit-logs') }}" href="{{ route('admin.dashboard.audit-logs') }}">
                        <i class="bi bi-journal-text"></i> Audit Logs
                    </a>
                    <a class="nav-link {{ $is('admin.backup.index') }}" href="{{ route('admin.backup.index') }}">
                        <i class="bi bi-cloud-download"></i> Backup & Recovery
                    </a>
                    <a class="nav-link {{ $is('admin.rollover.*') }}" href="{{ route('admin.rollover.create') }}">
                        <i class="bi bi-arrow-repeat"></i> Rollover Tahun Ajaran
                    </a>
                    
                    <div class="text-uppercase text-muted fw-semibold small mb-1 mt-3">Manajemen Data</div>
                    <a class="nav-link {{ $is('admin.users.index') }}" href="{{ route('admin.users.index') }}">
                        <i class="bi bi-people-fill"></i> Manajemen User
                    </a>
                    <a class="nav-link {{ $is('admin.guru.index') }}" href="{{ route('admin.guru.index') }}">
                        <i class="bi bi-person-badge"></i> Manajemen Guru
                    </a>
                    <a class="nav-link {{ $is('admin.siswa.index') }}" href="{{ route('admin.siswa.index') }}">
                        <i class="bi bi-people"></i> Manajemen Siswa
                    </a>
                    <a class="nav-link {{ $is('admin.kelas.index') }}" href="{{ route('admin.kelas.index') }}">
                        <i class="bi bi-door-closed"></i> Manajemen Kelas
                    </a>
                    <a class="nav-link {{ $is('admin.kategori.index') }}" href="{{ route('admin.kategori.index') }}">
                        <i class="bi bi-tags"></i> Manajemen Kategori
                    </a>
                    <a class="nav-link {{ $is('admin.rekomendasi.index') }}" href="{{ route('admin.rekomendasi.index') }}">
                        <i class="bi bi-list-check"></i> Manajemen Rekomendasi
                    </a>
                    <a class="nav-link {{ $is('admin.roster.*') }}" href="{{ route('admin.roster.index') }}">
                        <i class="bi bi-upload"></i> Import Roster
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
                    {{-- Refleksi Siswa (baru) --}}
                    <a class="nav-link {{ $is('guru.refleksi.*') }}" href="{{ route('guru.refleksi.index') }}">Refleksi Siswa</a>

                    {{-- === BARU: Slot Konseling (hanya untuk Guru BK) === --}}
                    @if ($guruJenis === 'bk')
                        <a class="nav-link {{ $is('guru.bk.slots.view') }} {{ $is('guru.guru_bk.slots.*') }}"
                            href="{{ route('guru.guru_bk.slots.view') }}">Slot Konseling (BK)</a>
                        <a class="nav-link {{ $is('guru.bk.refleksi-history') }}" href="{{ route('guru.bk.refleksi-history') }}">History Refleksi</a>
                    @endif

                    <div class="text-uppercase text-muted fw-semibold small mb-1 mt-3">Akun</div>
                    <a class="nav-link {{ $is('guru.profile.*') }}" href="{{ route('guru.profile.index') }}">
                        <i class="bi bi-person-circle"></i> Profil Saya
                    </a>
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
