@extends('layouts.guru')
@section('title', 'Tren Emosi Siswa')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h4 mb-1">Tren Emosi Siswa</h2>
            <div class="text-muted small">{{ $guruJenis === 'wali_kelas' ? 'Data khusus untuk kelas Anda' : 'Data seluruh siswa' }}</div>
        </div>
        <div>
            <a href="{{ url('/guru') }}" class="btn btn-outline-secondary btn-sm">← Kembali ke Dashboard</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3 align-items-end filter-row active" id="rowSummary">
                <div class="col-12 col-md-3">
                    <label class="form-label">Jenis</label>
                    <select id="jenis" class="form-select">
                        @if($guruJenis === 'wali_kelas')
                            <option value="per_siswa" selected>Per siswa</option>
                            <option value="seluruh_siswa">Seluruh siswa</option>
                        @else
                            <option value="per_kelas" selected>Per kelas</option>
                            <option value="seluruh_kelas">Seluruh kelas</option>
                        @endif
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Periode</label>
                    <select id="period" class="form-select">
                        <option value="daily" {{ $defaultPeriod==='daily' ? 'selected' : '' }}>Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="monthly">Bulanan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Tanggal (anchor)</label>
                    <input type="date" class="form-control" id="from">
                </div>
                <div class="col-12 col-md-3 d-none">
                    <label class="form-label">Sampai</label>
                    <input type="date" class="form-control" id="to">
                </div>
                {{-- Baris pertama ringkas: tanpa kelas/siswa karena sudah ada di baris 2 & 3 --}}
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-primary" id="btnLoad">Muat</button>
                </div>
            </div>
            {{-- FILTER BAR 2: Timeline Kelas (BK) --}}
            @if($guruJenis === 'bk')
            <div class="row g-3 align-items-end mt-3 filter-row" id="rowKelas">
                <div class="col-12 col-md-3">
                    <label class="form-label">Kelas (Timeline)</label>
                    <input type="text" class="form-control form-control-sm mb-1" id="kelas-search2" placeholder="Cari kelas...">
                    <select id="kelasSel2" class="form-select">
                        <option value="">— Pilih kelas —</option>
                        @foreach(($kelasOptions ?? collect()) as $k)
                            <option value="{{ $k['id'] }}">{{ $k['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Periode</label>
                    <select id="kelas-period" class="form-select">
                        <option value="daily" selected>Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="monthly">Bulanan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Tanggal (anchor)</label>
                    <input type="date" class="form-control" id="kelas-from">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-primary" id="btnLoadKelas">Muat</button>
                </div>
            </div>
            @endif

            {{-- FILTER BAR 3: Timeline Siswa (BK & WK) --}}
            <div class="row g-3 align-items-end mt-3 filter-row" id="rowSiswa">
                @if($guruJenis === 'bk')
                <div class="col-12 col-md-3">
                    <label class="form-label">Kelas</label>
                    <input type="text" class="form-control form-control-sm mb-1" id="kelas-search" placeholder="Cari kelas...">
                    <select id="kelasSel" class="form-select">
                        <option value="">— Pilih kelas —</option>
                        @foreach(($kelasOptions ?? collect()) as $k)
                            <option value="{{ $k['id'] }}">{{ $k['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-12 col-md-3">
                    <label class="form-label">Siswa</label>
                    <input type="text" class="form-control form-control-sm mb-1" id="siswa-search2" placeholder="Cari siswa...">
                    <select id="siswaSel2" class="form-select">
                        <option value="">— Pilih siswa —</option>
                        @foreach(($studentOptions ?? collect()) as $s)
                            <option value="{{ $s['id'] }}">{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Periode</label>
                    <select id="siswa-period" class="form-select">
                        <option value="daily" selected>Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="monthly">Bulanan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-primary" id="btnLoadSiswa">Muat</button>
                </div>
            </div>

            <hr>
            <canvas id="emosiChart" height="120"></canvas>
            <div class="small text-muted mt-2" id="meta"></div>
            @if($guruJenis === 'bk')
            <div class="row mt-3" id="detailRow">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <div class="fw-semibold mb-2">Detail per Kelas</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="detailTableKelas">
                            <thead><tr><th>Kelas</th><th class="text-center">Avg</th><th class="text-center">Min</th><th class="text-center">Max</th><th class="text-center">N</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-semibold">Detail per Siswa</div>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="detailKelasFilter" class="form-select form-select-sm" style="max-width:260px">
                                <option value="">— Pilih kelas —</option>
                                @foreach(($kelasOptions ?? collect()) as $k)
                                    <option value="{{ $k['id'] }}">{{ $k['label'] }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm" id="btnDetailSiswaReload">Muat</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="detailTableSiswa">
                            <thead><tr><th>Siswa</th><th class="text-center">Avg</th><th class="text-center">Min</th><th class="text-center">Max</th><th class="text-center">N</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            @else
            <div class="mt-3" id="detailBox">
                <div class="fw-semibold mb-2">Detail</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="detailTable">
                        <thead><tr><th>Label</th><th class="text-center">Avg</th><th class="text-center">Min</th><th class="text-center">Max</th><th class="text-center">N</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .filter-row{ border:2px solid #0b3a64; border-radius:12px; padding:12px; background:transparent; }
    .filter-row.active{ background:#e9f1f9; }
    .filter-row .form-label{ margin-bottom:4px; }
</style>
<script>
(function(){
    const $ = (s)=>document.querySelector(s);
    // Summary row elements
    const jenisSel = $('#jenis');
    const periodSel = $('#period');
    const fromEl = $('#from');
    const btn = $('#btnLoad');
    const meta = $('#meta');
    // Existing selectors from first row (kept for compatibility)
    const kelasSel = $('#kelasSel');
    const siswaSel = $('#siswaSel');
    const kelasSearch = $('#kelas-search');
    const siswaSearch = $('#siswa-search');
    // Timeline rows
    const kelasSel2 = $('#kelasSel2');
    const kelasSearch2 = $('#kelas-search2');
    const kelasPeriodSel = $('#kelas-period');
    const kelasFromEl = $('#kelas-from');
    const btnLoadKelas = $('#btnLoadKelas');
    const siswaSel2 = $('#siswaSel2');
    const siswaSearch2 = $('#siswa-search2');
    const siswaPeriodSel = $('#siswa-period');
    const siswaFromEl = $('#siswa-from');
    const btnLoadSiswa = $('#btnLoadSiswa');
    const detailTableBody = document.querySelector('#detailTable tbody');
    const detailKelasBody = document.querySelector('#detailTableKelas tbody');
    const detailSiswaBody = document.querySelector('#detailTableSiswa tbody');
    const detailKelasFilter = document.querySelector('#detailKelasFilter');
    const btnDetailSiswaReload = document.querySelector('#btnDetailSiswaReload');

    let chart;
    const scoreEmoji = (y)=>{
        const s = Math.round(y);
        if(s>=10) return '🤩';
        if(s>=9) return '😍';
        if(s>=8) return '😎';
        if(s>=7) return '😊';
        if(s>=6) return '😴';
        if(s>=5) return '😐';
        if(s>=4) return '😟';
        if(s>=3) return '😔';
        if(s>=2) return '😭';
        return '😓';
    };
    const lerp=(a,b,t)=>a+(b-a)*t;
    const scoreToColor=(s)=>{
        const clamp=Math.max(1,Math.min(10,s||5));
        const t=(clamp-1)/9;
        const stops=[{r:239,g:68,b:68},{r:245,g:158,b:11},{r:22,g:163,b:74}];
        const s1=t<0.5?stops[0]:stops[1];
        const s2=t<0.5?stops[1]:stops[2];
        const tt=t<0.5?(t/0.5):((t-0.5)/0.5);
        const r=Math.round(lerp(s1.r,s2.r,tt));
        const g=Math.round(lerp(s1.g,s2.g,tt));
        const b=Math.round(lerp(s1.b,s2.b,tt));
        return `rgb(${r}, ${g}, ${b})`;
    };

    function render(labels, values, colors, axisMode){
        const ctx=document.getElementById('emosiChart').getContext('2d');
        if(chart) chart.destroy();
        const isVertical = axisMode && axisMode.indexOf('vertical')>=0;
        const options = isVertical ? {
            indexAxis:'x',
            scales:{
                x:{ ticks:{ autoSkip:false } },
                y:{ suggestedMin:1, suggestedMax:10, ticks:{ stepSize:1, callback:(v)=>scoreEmoji(v) } }
            },
            plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx)=>` ${scoreEmoji(ctx.parsed.y)}  avg ${ctx.parsed.y}` } } }
        } : {
            indexAxis:'y',
            scales:{
                x:{ suggestedMin:1, suggestedMax:10, ticks:{ stepSize:1, callback:(v)=>scoreEmoji(v) } },
                y:{ ticks:{ autoSkip:false } }
            },
            plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx)=>` ${scoreEmoji(ctx.parsed.x)}  avg ${ctx.parsed.x}` } } }
        };
        chart=new Chart(ctx,{ type:'bar', data:{ labels, datasets:[{ label:'Rata-rata Emosi', data:values, backgroundColor:(colors&&colors.length===values.length)?colors:values.map(scoreToColor), borderWidth:0, borderRadius:6 }] }, options });
    }

    function fillDetail(items){
        if(!detailTableBody) return;
        detailTableBody.innerHTML=(items||[]).map(it=>`<tr>
            <td><span class="badge" style="background:${it.color};">&nbsp;</span> ${it.label}</td>
            <td class="text-center">${it.avg ?? '—'}</td>
            <td class="text-center">${it.min ?? '—'}</td>
            <td class="text-center">${it.max ?? '—'}</td>
            <td class="text-center">${it.count ?? 0}</td>
        </tr>`).join('');
    }
    function fillDetailKelas(items){
        if(!detailKelasBody) return;
        detailKelasBody.innerHTML=(items||[]).map(it=>`<tr>
            <td><span class="badge" style="background:${it.color};">&nbsp;</span> ${it.label}</td>
            <td class="text-center">${it.avg ?? '—'}</td>
            <td class="text-center">${it.min ?? '—'}</td>
            <td class="text-center">${it.max ?? '—'}</td>
            <td class="text-center">${it.count ?? 0}</td>
        </tr>`).join('');
    }
    function fillDetailSiswa(items){
        if(!detailSiswaBody) return;
        detailSiswaBody.innerHTML=(items||[]).map(it=>`<tr>
            <td><span class="badge" style="background:${it.color};">&nbsp;</span> ${it.label}</td>
            <td class="text-center">${it.avg ?? '—'}</td>
            <td class="text-center">${it.min ?? '—'}</td>
            <td class="text-center">${it.max ?? '—'}</td>
            <td class="text-center">${it.count ?? 0}</td>
        </tr>`).join('');
    }

    function filterSelect(selectEl, q){
        if(!selectEl) return;
        const lower=(q||'').toLowerCase();
        Array.from(selectEl.options).forEach(opt=>{
            if(!opt.value) return;
            opt.hidden=!opt.text.toLowerCase().includes(lower);
        });
    }

    function setElementsDisabled(els, disabled){
        (els||[]).forEach(el=>{ if(!el) return; try { el.disabled = !!disabled; } catch(e){} });
    }
    function setMode(mode){
        const summaryEls = [jenisSel, periodSel, fromEl]; // keep btn enabled for mode switching
        const kelasEls = [kelasSel2||kelasSel, kelasSearch2||null, kelasPeriodSel, kelasFromEl];
        const siswaEls = [kelasSel||null, siswaSel2||siswaSel, siswaSearch2||null, siswaPeriodSel];
        if(mode==='summary'){
            setElementsDisabled(summaryEls, false);
            setElementsDisabled(kelasEls, true);
            setElementsDisabled(siswaEls, true);
            document.querySelector('#rowSummary')?.classList.add('active');
            document.querySelector('#rowKelas')?.classList.remove('active');
            document.querySelector('#rowSiswa')?.classList.remove('active');
        } else if(mode==='kelas'){
            setElementsDisabled(summaryEls, true);
            setElementsDisabled(kelasEls, false);
            setElementsDisabled(siswaEls, true);
            document.querySelector('#rowSummary')?.classList.remove('active');
            document.querySelector('#rowKelas')?.classList.add('active');
            document.querySelector('#rowSiswa')?.classList.remove('active');
        } else if(mode==='siswa'){
            setElementsDisabled(summaryEls, true);
            setElementsDisabled(kelasEls, true);
            setElementsDisabled(siswaEls, false);
            document.querySelector('#rowSummary')?.classList.remove('active');
            document.querySelector('#rowKelas')?.classList.remove('active');
            document.querySelector('#rowSiswa')?.classList.add('active');
        }
    }

    async function loadSummary(){
        const params=new URLSearchParams();
        const period=periodSel.value||'daily';
        const jenis=jenisSel.value;
        params.set('period',period);
        if(fromEl.value) params.set('from',fromEl.value);
        params.set('detail','1');
        @if($guruJenis === 'wali_kelas')
            if(jenis==='per_siswa') params.set('group','siswa');
            else params.set('group','time');
        @else
            // BK Row1: seluruh_kelas => overall timeline; per_kelas => snapshot per kelas
            if(jenis==='seluruh_kelas') params.set('group','time');
            else if(jenis==='per_kelas') params.set('group','kelas');
        @endif
        if(params.get('group')==='time'){
            if(period==='daily') params.set('span','week');
            else if(period==='weekly') params.set('span','month');
            else if(period==='monthly') params.set('span','year');
        }
        const res=await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`,{ headers:{ 'Accept':'application/json' } });
        const data=await res.json();
        const axis = params.get('group')==='time' ? 'time-vertical' : 'vertical';
        render(data.labels, (data.datasets?.[0]?.data)||[], data.colors||[], axis);
        meta.textContent=`Periode: ${data.period} | ${data.from} s/d ${data.to}`;
        if(!fromEl.value) fromEl.value=data.from;
        fillDetail(data.items||[]);
        @if($guruJenis === 'bk')
            // Load details for BK
            await loadDetailKelas(period, fromEl.value||data.from);
            await loadDetailSiswa(period, fromEl.value||data.from, detailKelasFilter?.value||'');
        @endif
    }

    async function loadKelasTimeline(){
        const sel = kelasSel2 || kelasSel; // prefer new row, fallback old
        if(!sel || !sel.value) return;
        const params=new URLSearchParams();
        params.set('group','time');
        params.set('kelas_id', sel.value);
        const kPeriod = (kelasPeriodSel?.value)||'daily';
        params.set('period', kPeriod);
        if(kelasFromEl?.value) params.set('from', kelasFromEl.value);
        params.set('detail','1');
        if(params.get('group')==='time'){
            if(kPeriod==='daily') params.set('span','week');
            else if(kPeriod==='weekly') params.set('span','month');
            else if(kPeriod==='monthly') params.set('span','year');
        }
        const res=await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`,{ headers:{ 'Accept':'application/json' } });
        const data=await res.json();
        render(data.labels, (data.datasets?.[0]?.data)||[], data.colors||[], 'time-vertical');
        meta.textContent=`Timeline Kelas • ${data.period} | ${data.from} s/d ${data.to}`;
        fillDetail(data.items||[]);
        @if($guruJenis === 'bk')
            // Sync kelas filter to selected timeline kelas
            if(detailKelasFilter){ detailKelasFilter.value = sel.value; }
            await loadDetailKelas((kelasPeriodSel?.value)||'daily', kelasFromEl?.value||data.from);
            await loadDetailSiswa((kelasPeriodSel?.value)||'daily', kelasFromEl?.value||data.from, sel.value);
        @endif
    }

    async function loadSiswaTimeline(){
        const sel = siswaSel2 || siswaSel; // prefer new row, fallback old
        if(!sel || !sel.value) return;
        const params=new URLSearchParams();
        params.set('group','time');
        params.set('siswa_kelas_id', sel.value);
        const sPeriod = (siswaPeriodSel?.value)||'daily';
        params.set('period', sPeriod);
        params.set('detail','1');
        if(params.get('group')==='time'){
            if(sPeriod==='daily') params.set('span','week');
            else if(sPeriod==='weekly') params.set('span','month');
            else if(sPeriod==='monthly') params.set('span','year');
        }
        const res=await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`,{ headers:{ 'Accept':'application/json' } });
        const data=await res.json();
        render(data.labels, (data.datasets?.[0]?.data)||[], data.colors||[], 'siswa-vertical');
        meta.textContent=`Timeline Siswa • ${data.period} | ${data.from} s/d ${data.to}`;
        fillDetail(data.items||[]);
        @if($guruJenis === 'bk')
            await loadDetailKelas((siswaPeriodSel?.value)||'daily', data.from);
            await loadDetailSiswa((siswaPeriodSel?.value)||'daily', data.from, detailKelasFilter?.value||'');
        @endif
    }

    // Wire events
    btn?.addEventListener('click', ()=>{ setMode('summary'); loadSummary(); });
    periodSel?.addEventListener('change', ()=>{ fromEl.value=''; setMode('summary'); loadSummary(); });
    jenisSel?.addEventListener('change', ()=>{ setMode('summary'); loadSummary(); });
    // search filters
    kelasSearch?.addEventListener('input', (e)=>filterSelect(kelasSel, e.target.value));
    siswaSearch?.addEventListener('input', (e)=>filterSelect(siswaSel, e.target.value));
    kelasSearch2?.addEventListener('input', (e)=>filterSelect(kelasSel2, e.target.value));
    siswaSearch2?.addEventListener('input', (e)=>filterSelect(siswaSel2, e.target.value));
    // load dependent siswa list when kelas chosen (BK)
    (kelasSel2||kelasSel)?.addEventListener('change', async ()=>{
        const ksel = (kelasSel2||kelasSel);
        const ssel = (siswaSel2||siswaSel);
        if(!ksel?.value || !ssel) return;
        const url = `{{ route('guru.tren_emosi.siswa') }}?kelas_id=${encodeURIComponent(ksel.value)}`;
        const res = await fetch(url, { headers:{ 'Accept':'application/json' } });
        const data = await res.json();
        const opts = (data.items||[]).map(it=>`<option value="${it.id}">${it.label}</option>`).join('');
        ssel.innerHTML = `<option value="">— Pilih siswa —</option>` + opts;
    });
    btnLoadKelas?.addEventListener('click', ()=>{ setMode('kelas'); loadKelasTimeline(); });
    kelasPeriodSel?.addEventListener('change', ()=>{ setMode('kelas'); loadKelasTimeline(); });
    btnLoadSiswa?.addEventListener('click', ()=>{ setMode('siswa'); loadSiswaTimeline(); });
    siswaPeriodSel?.addEventListener('change', ()=>{ setMode('siswa'); loadSiswaTimeline(); });
    (siswaSel2||siswaSel)?.addEventListener('change', ()=>{/* wait for click */});

    @if($guruJenis === 'bk')
    async function loadDetailKelas(period, from){
        if(!detailKelasBody) return;
        const p=new URLSearchParams();
        p.set('group','kelas');
        p.set('period', period||'daily');
        if(from) p.set('from', from);
        p.set('detail','1');
        const res=await fetch(`{{ route('guru.tren_emosi.data') }}?${p.toString()}`,{ headers:{ 'Accept':'application/json' } });
        const data=await res.json();
        fillDetailKelas(data.items||[]);
    }
    async function loadDetailSiswa(period, from, kelasId){
        if(!detailSiswaBody) return;
        const p=new URLSearchParams();
        p.set('group','siswa');
        p.set('period', period||'daily');
        if(from) p.set('from', from);
        if(kelasId) p.set('kelas_id', kelasId);
        p.set('detail','1');
        const res=await fetch(`{{ route('guru.tren_emosi.data') }}?${p.toString()}`,{ headers:{ 'Accept':'application/json' } });
        const data=await res.json();
        fillDetailSiswa(data.items||[]);
    }
    detailKelasFilter?.addEventListener('change', ()=>{
        loadDetailSiswa(periodSel?.value||'daily', fromEl?.value||'', detailKelasFilter.value||'');
    });
    btnDetailSiswaReload?.addEventListener('click', ()=>{
        loadDetailSiswa(periodSel?.value||'daily', fromEl?.value||'', detailKelasFilter.value||'');
    });
    @endif

    // Default load like dashboards
    setMode('summary');
    loadSummary();
})();
</script>
@endpush
