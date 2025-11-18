@extends('layouts.admin')

@section('title', 'Profil Saya')

@section('page-header')
<div class="d-flex align-items-center justify-content-between">
  <h1 class="h4 m-0">👤 Profil Saya</h1>
</div>
@endsection

@section('content')
<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Informasi Akun</strong></div>
      <div class="card-body">
        <div class="mb-2"><strong>Nama:</strong> {{ $user->name }}</div>
        <div class="mb-2"><strong>Identifier:</strong> {{ $user->identifier }}</div>
        <div class="mb-2"><strong>Email:</strong> {{ $user->email }}</div>
        <div class="mb-2"><strong>Peran:</strong> Guru</div>
        <div class="mb-2"><strong>Jenis Guru:</strong> {{ $user->guru->jenis ?? '-' }}</div>
        <div class="mb-2"><strong>Jenis Kelamin:</strong>
          @if($user->jenis_kelamin==='L') Laki-laki @elseif($user->jenis_kelamin==='P') Perempuan @else - @endif
        </div>
        @if($user->password_changed_at)
          <div class="text-muted small">Password diubah: {{ $user->password_changed_at->diffForHumans() }}</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Aksi</strong></div>
      <div class="card-body">
        <div class="d-flex gap-2">
          <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditProfil">Edit Profil</button>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUbahPassword">Ubah Password</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit Profil -->
<div class="modal fade" id="modalEditProfil" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="{{ route('guru.profile.update') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Edit Profil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="{{ $user->email }}" required autocomplete="off">
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div>
  </div>

<!-- Modal Ubah Password -->
<div class="modal fade" id="modalUbahPassword" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="{{ route('guru.profile.password') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Ubah Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3" id="groupCurrentPassword">
          @if(!$user->password_changed_at && $user->initial_password)
            @php($decryptedToken = \Illuminate\Support\Facades\Crypt::decryptString($user->initial_password))
            <label class="form-label">Token password</label>
            <div class="input-group">
              <input type="text" name="current_password" class="form-control" value="{{ $decryptedToken }}" readonly>
              <button type="button" class="btn btn-outline-secondary" id="toggleTokenVisibility">Sembunyikan</button>
            </div>
            <div class="form-text">Gunakan token ini untuk menetapkan password baru pertama kali.</div>
          @else
            <label class="form-label">Password lama</label>
            <div class="input-group">
              <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
              <button type="button" class="btn btn-outline-secondary" id="toggleCurrentPwd">Tampilkan</button>
            </div>
          @endif
        </div>
        <div class="mb-3">
          <label class="form-label">Password Baru</label>
          <div class="input-group">
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
            <button type="button" class="btn btn-outline-secondary" id="toggleNewPwd">Tampilkan</button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Konfirmasi Password Baru</label>
          <div class="input-group">
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
            <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPwd">Tampilkan</button>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div>
</div>
@endsection
@push('scripts')
<script>
// Auto-open password modal if query param pwd=1 is present
if (new URLSearchParams(window.location.search).get('pwd') === '1') {
  document.addEventListener('DOMContentLoaded', ()=>{
    const modalEl = document.getElementById('modalUbahPassword');
    if (modalEl) {
      const m = new bootstrap.Modal(modalEl);
      m.show();
    }
  });
}
document.getElementById('modalUbahPassword')?.addEventListener('shown.bs.modal', ()=>{
  const toggleVisibility = (btnId, inputSelector) => {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    const input = document.querySelector(inputSelector);
    if (!input) return;
    btn.addEventListener('click', ()=>{
      if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Sembunyikan';
      } else {
        input.type = 'password';
        btn.textContent = 'Tampilkan';
      }
    });
  };
  toggleVisibility('toggleCurrentPwd', 'input[name=current_password]');
  toggleVisibility('toggleNewPwd', 'input[name=password]');
  toggleVisibility('toggleConfirmPwd', 'input[name=password_confirmation]');
  const tokenBtn = document.getElementById('toggleTokenVisibility');
  if (tokenBtn) {
    const tokenInput = document.querySelector('input[name=current_password]');
    tokenBtn.addEventListener('click', ()=>{
      if (!tokenInput) return;
      if (tokenInput.readOnly && tokenInput.type === 'text') {
        // toggle hide by replacing value with bullets visually (not altering actual); simpler: switch to password type
        tokenInput.type = 'password';
        tokenBtn.textContent = 'Tampilkan';
      } else if (tokenInput.type === 'password') {
        tokenInput.type = 'text';
        tokenBtn.textContent = 'Sembunyikan';
      }
    });
  }
});
</script>
@endpush
