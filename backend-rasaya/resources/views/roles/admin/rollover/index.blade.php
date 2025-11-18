@extends('layouts.admin')

@section('title','Rollover Tahun Ajaran')

@section('page-header')
<div>
    <h3 class="mb-1">Rollover Tahun Ajaran</h3>
    <div class="text-muted">Salin data dari tahun ajaran sebelumnya ke tahun ajaran aktif.</div>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
  <div class="card-body">
    <form id="dryRunForm" method="post" action="{{ route('admin.rollover.dryrun') }}">
      @csrf
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Tahun Ajaran Sumber</label>
          <select name="source_year_id" class="form-select" required>
            @foreach($years as $y)
              <option value="{{ $y->id }}">{{ $y->nama ?? ($y->tahun_mulai.'/'.$y->tahun_selesai) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Tahun Ajaran Tujuan</label>
          <select name="target_year_id" class="form-select" required>
            @foreach($years as $y)
              <option value="{{ $y->id }}" @selected($activeYear && $activeYear->id===$y->id)>{{ $y->nama ?? ($y->tahun_mulai.'/'.$y->tahun_selesai) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Salin</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="copy[]" value="jurusan" checked>
            <label class="form-check-label">Jurusan</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="copy[]" value="kelas" checked>
            <label class="form-check-label">Kelas</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="copy[]" value="wali_kelas">
            <label class="form-check-label">Wali Kelas</label>
          </div>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Dry‑run</button>
        <button type="button" id="runBtn" class="btn btn-primary" disabled>Jalankan</button>
      </div>
    </form>
    <div class="row mt-3">
      <div class="col-md-6">
        <h6 class="mb-2">Ringkasan</h6>
        <pre id="summary" style="display:none" class="bg-light p-2 border rounded small"></pre>
      </div>
      <div class="col-md-6">
        <h6 class="mb-2">Konflik</h6>
        <pre id="conflicts" style="display:none" class="bg-light p-2 border rounded small"></pre>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const form = document.getElementById('dryRunForm');
const summaryEl = document.getElementById('summary');
const conflictsEl = document.getElementById('conflicts');
const runBtn = document.getElementById('runBtn');
form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  runBtn.disabled = true;
  summaryEl.style.display='none';
  conflictsEl.style.display='none';
  const res = await fetch(form.action,{method:'POST',body:new FormData(form)});
  const json = await res.json();
  summaryEl.textContent = JSON.stringify(json.summary || {}, null, 2);
  conflictsEl.textContent = JSON.stringify(json.conflicts || [], null, 2);
  summaryEl.style.display='block';
  conflictsEl.style.display='block';
  runBtn.disabled = false;
});
runBtn.addEventListener('click', ()=>{
  form.action = "{{ route('admin.rollover.run') }}";
  form.submit();
});
</script>
@endpush
