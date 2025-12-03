@props(['role' => 'guest'])
@php
    $is = fn($name) => request()->routeIs($name) ? 'active' : '';
    $guruJenis = optional(auth()->user()->guru)->jenis; // 'bk' | 'wali_kelas' | null
@endphp

<aside class="col-12 col-md-3 col-lg-2 p-0 sidebar bg-light border-end" style="min-height: 100vh; position: sticky; top: 0; height: 100vh; overflow-y: auto;">
    <div class="p-3">
        {{-- Logo RASAYA - Centered and Large --}}
        <div class="text-center mb-4 pb-3 border-bottom">
            @if($role === 'admin')
                <div class="display-6 fw-bold mb-1" style="color: #1e3a8a; letter-spacing: 2px;">
                    RASAYA
                </div>
                <small class="d-block text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 1px;">Portal Admin</small>
            @else
                <div class="display-6 fw-bold mb-1" style="color: #ec4899; letter-spacing: 2px;">
                    RASAYA
                </div>
                <small class="d-block text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 1px;">Portal Guru</small>
            @endif
        </div>

        @switch($role)
            {{-- ================= ADMIN ================= --}}
            @case('admin')
                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Utama</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') && !request()->routeIs('admin.dashboard.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </nav>
                </div>

                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Analitik & Log</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ $is('admin.dashboard.login-history') }}" href="{{ route('admin.dashboard.login-history') }}">
                            <i class="bi bi-clock-history me-2"></i> Riwayat Masuk
                        </a>
                        <a class="nav-link {{ $is('admin.dashboard.refleksi-history') }}" href="{{ route('admin.dashboard.refleksi-history') }}">
                            <i class="bi bi-journal-check me-2"></i> Riwayat Refleksi
                        </a>
                        <a class="nav-link {{ $is('admin.dashboard.mood-history') }}" href="{{ route('admin.dashboard.mood-history') }}">
                            <i class="bi bi-emoji-smile me-2"></i> Riwayat Mood
                        </a>
                        <a class="nav-link {{ $is('admin.dashboard.audit-logs') }}" href="{{ route('admin.dashboard.audit-logs') }}">
                            <i class="bi bi-journal-text me-2"></i> Log Audit
                        </a>
                    </nav>
                </div>

                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Data Master</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ $is('admin.users.index') }}" href="{{ route('admin.users.index') }}">
                            <i class="bi bi-people-fill me-2"></i> Pengguna
                        </a>
                        <a class="nav-link {{ $is('admin.guru.index') }}" href="{{ route('admin.guru.index') }}">
                            <i class="bi bi-person-badge me-2"></i> Guru
                        </a>
                        <a class="nav-link {{ $is('admin.siswa.index') }}" href="{{ route('admin.siswa.index') }}">
                            <i class="bi bi-people me-2"></i> Siswa
                        </a>
                        <a class="nav-link {{ $is('admin.kelas.index') }}" href="{{ route('admin.kelas.index') }}">
                            <i class="bi bi-door-closed me-2"></i> Kelas
                        </a>
                        <a class="nav-link {{ $is('admin.kategori.index') }}" href="{{ route('admin.kategori.index') }}">
                            <i class="bi bi-tags me-2"></i> Kategori Masalah
                        </a>
                        <a class="nav-link {{ $is('admin.rekomendasi.index') }}" href="{{ route('admin.rekomendasi.index') }}">
                            <i class="bi bi-list-check me-2"></i> Rekomendasi
                        </a>
                    </nav>
                </div>

                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Sistem</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ $is('admin.roster.*') }}" href="{{ route('admin.roster.index') }}">
                            <i class="bi bi-upload me-2"></i> Impor Roster
                        </a>
                        <a class="nav-link {{ $is('admin.backup.index') }}" href="{{ route('admin.backup.index') }}">
                            <i class="bi bi-cloud-download me-2"></i> Cadangkan & Pulihkan
                        </a>
                        <a class="nav-link {{ $is('admin.rollover.*') }}" href="{{ route('admin.rollover.create') }}">
                            <i class="bi bi-arrow-repeat me-2"></i> Rollover Tahun
                        </a>
                    </nav>
                </div>
            @break

            {{-- ================= GURU (BK & WALI KELAS) ================= --}}
            @case('guru')
                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Utama</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ $is('guru.bk.dashboard') }} {{ $is('guru.wk.dashboard') }}" href="{{ url('/guru') }}">
                            <i class="bi bi-grid me-2"></i> Dashboard
                        </a>
                        <a class="nav-link {{ $is('guru.profile.*') }}" href="{{ route('guru.profile.index') }}">
                            <i class="bi bi-person-circle me-2"></i> Profil Saya
                        </a>
                    </nav>
                </div>

                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Monitoring Siswa</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ $is('guru.tren_emosi.*') }}" href="{{ route('guru.tren_emosi.index') }}">
                            <i class="bi bi-graph-up me-2"></i> Tren Emosi
                        </a>
                        <a class="nav-link {{ $is('guru.analisis.*') }} {{ $is('guru.bk.analisis.*') }}" href="{{ route('guru.analisis.index') }}">
                            <i class="bi bi-search me-2"></i> Analisis Masalah
                        </a>
                        <a class="nav-link {{ $is('guru.refleksi.*') }}" href="{{ route('guru.refleksi.index') }}">
                            <i class="bi bi-journal-text me-2"></i> Refleksi Siswa
                        </a>
                    </nav>
                </div>

                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Input Data</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link {{ $is('guru.observasi.*') }}" href="{{ route('guru.observasi.index') }}">
                            <i class="bi bi-pencil-square me-2"></i> Observasi Guru
                        </a>
                    </nav>
                </div>

                {{-- Riwayat Data (Hanya BK) --}}
                @if ($guruJenis === 'bk')
                    <div class="mb-3">
                        <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Riwayat Data</div>
                        <nav class="nav nav-pills flex-column gap-1">
                            <a class="nav-link {{ $is('guru.bk.refleksi-history') }}" href="{{ route('guru.bk.refleksi-history') }}">
                                <i class="bi bi-clock-history me-2"></i> Riwayat Refleksi
                            </a>
                        </nav>
                    </div>

                    <div class="mb-3">
                        <div class="small fw-bold text-uppercase text-muted mb-2 ps-3" style="font-size: 0.75rem;">Konseling (BK)</div>
                        <nav class="nav nav-pills flex-column gap-1">
                            <a class="nav-link {{ $is('guru.bk.slots.view') }} {{ $is('guru.guru_bk.slots.*') }}" href="{{ route('guru.guru_bk.slots.view') }}">
                                <i class="bi bi-calendar-check me-2"></i> Slot Konseling
                            </a>
                        </nav>
                    </div>
                @endif
            @break

            @default
                <nav class="nav nav-pills flex-column gap-1">
                    <a class="nav-link" href="{{ url('/') }}">
                        <i class="bi bi-house me-2"></i> Beranda
                    </a>
                </nav>
        @endswitch
    </div>
</aside>