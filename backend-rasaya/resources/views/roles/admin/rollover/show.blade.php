@extends('layouts.admin')

@section('title','Rollover Progress')

@section('page-header')
<div>
  <h3 class="mb-1">Rollover Progress</h3>
  <div class="text-muted">Pantau status proses penyalinan data.</div>
</div>
@endsection

@section('content')
<div class="card">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Run ID</dt>
      <dd class="col-sm-9">{{ $run->id }}</dd>

      <dt class="col-sm-3">Sumber → Tujuan</dt>
      <dd class="col-sm-9">{{ $run->source_year_id }} → {{ $run->target_year_id }}</dd>

      <dt class="col-sm-3">Status</dt>
      <dd class="col-sm-9"><span id="statusText">{{ $run->status }}</span></dd>

      <dt class="col-sm-3">Progress</dt>
      <dd class="col-sm-9"><span id="progressText">{{ $run->progress }}%</span></dd>

      <dt class="col-sm-3">Log</dt>
      <dd class="col-sm-9"><pre id="logPre" class="bg-light p-2 border rounded small">{{ json_encode($run->log, JSON_PRETTY_PRINT) }}</pre></dd>

      <dt class="col-sm-3">Error</dt>
      <dd class="col-sm-9"><pre id="errPre" class="bg-light p-2 border rounded small">{{ $run->error }}</pre></dd>
    </dl>
    <div class="mt-3 d-flex gap-2">
      <a class="btn btn-secondary" href="{{ route('admin.rollover.create') }}">Kembali</a>
      <button id="refreshBtn" class="btn btn-outline-primary" type="button">Refresh</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const statusText = document.getElementById('statusText');
const progressText = document.getElementById('progressText');
const logPre = document.getElementById('logPre');
const errPre = document.getElementById('errPre');
const refreshBtn = document.getElementById('refreshBtn');
const url = "{{ route('admin.rollover.json', $run->id) }}";
async function poll(){
  try{
    const res = await fetch(url);
    const j = await res.json();
    statusText.textContent = j.status;
    progressText.textContent = (j.progress||0) + '%';
    logPre.textContent = JSON.stringify(j.log||{}, null, 2);
    errPre.textContent = j.error||'';
  }catch(e){
    console.error(e);
  }
}
refreshBtn.addEventListener('click', poll);
const timer = setInterval(()=>{
  if(statusText.textContent==='succeeded' || statusText.textContent==='failed'){
    clearInterval(timer);
  } else { poll(); }
}, 2500);
</script>
@endpush
