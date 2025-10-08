{{-- resources/views/dashboards/admin.blade.php --}}
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Dashboard Admin — RASAYA</title>
  <link rel="stylesheet" href="https://unpkg.com/mvp.css">
</head>
<body>
<main>
  <h2>Dashboard Admin</h2>
  <p>Halo, {{ auth()->user()->name }} ({{ auth()->user()->identifier }})</p>

  <section>
    <header>
      <h3>Manajemen</h3>
    </header>

    <p>
      {{-- <a href="{{ route('admin.kelas.index') }}">📚 Kelola Kelas</a> --}}
      {{-- kalau mau tombol style --}}
      <a href="{{ route('admin.kelas.index') }}" role="button">📚 Kelola Kelas</a>
    </p>
  </section>
</main>
</body>
</html>
