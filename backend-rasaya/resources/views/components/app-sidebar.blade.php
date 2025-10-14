@props([
  // kirim role via :role="auth()->user()->role ?? 'guest'"
  'role' => 'guest'
])

@php
  $is = fn ($name) => request()->routeIs($name) ? 'active' : '';
@endphp

<aside class="col-12 col-md-3 col-lg-2 p-0 sidebar">
  <div class="p-3">
    <div class="text-uppercase text-muted fw-semibold small mb-2">Menu</div>

    @switch($role)
      {{-- ================= ADMIN ================= --}}
      @case('admin')
        <nav class="nav nav-pills flex-column gap-1">
          <a class="nav-link {{ $is('admin.dashboard') }}" href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>
          <a class="nav-link {{ $is('admin.kelas.index') }}" href="{{ route('admin.kelas.index') }}">📚 Manajemen Kelas</a>
          <a class="nav-link {{ $is('admin.kategori.index') }}" href="{{ route('admin.kategori.index') }}">🗂️ Manajemen Kategori</a>
          <a class="nav-link disabled">👩‍🏫 Data Guru (segera)</a>
          <a class="nav-link disabled">🧑‍🎓 Data Siswa (segera)</a>
        </nav>
      @break

      {{-- ================= GURU BK ================= --}}
      @case('guru')
        {{-- sementara statis (placeholder) --}}
        <nav class="nav nav-pills flex-column gap-1">
          <a class="nav-link" href="{{ url('/guru/guru_bk') }}">🏠 Dashboard</a>
          <a class="nav-link disabled">📥 Laporan Siswa (segera)</a>
          <a class="nav-link disabled">📊 Analitik Emosi (segera)</a>
        </nav>
      @break

      {{-- ================= WALI KELAS ================= --}}
      @case('wali_kelas')
        {{-- sementara statis (placeholder) --}}
        <nav class="nav nav-pills flex-column gap-1">
          <a class="nav-link" href="{{ url('/guru/wali_kelas') }}">🏠 Dashboard</a>
          <a class="nav-link disabled">👨‍👩‍👧‍👦 Daftar Siswa (segera)</a>
          <a class="nav-link disabled">📝 Catatan Wali (segera)</a>
        </nav>
      @break

      @default
        <nav class="nav nav-pills flex-column gap-1">
          <a class="nav-link" href="{{ url('/') }}">Home</a>
        </nav>
    @endswitch
  </div>
</aside>
