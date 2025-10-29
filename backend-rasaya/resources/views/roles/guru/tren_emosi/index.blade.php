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
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label">Periode</label>
                    <select id="period" class="form-select">
                        <option value="daily" {{ $defaultPeriod==='daily' ? 'selected' : '' }}>Harian (30 hari)</option>
                        <option value="weekly">Mingguan (12 minggu)</option>
                        <option value="monthly">Bulanan (12 bulan)</option>
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
                @if($guruJenis === 'bk')
                    <div class="col-12 col-md-3">
                        <label class="form-label">Kelas</label>
                        <input type="text" class="form-control form-control-sm mb-1" id="kelas-search" placeholder="Cari kelas...">
                        <select id="kelasSel" class="form-select">
                            <option value="">— Semua kelas —</option>
                            @foreach(($kelasOptions ?? collect()) as $k)
                                <option value="{{ $k['id'] }}">{{ $k['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Siswa (opsional)</label>
                        <input type="text" class="form-control form-control-sm mb-1" id="siswa-search" placeholder="Cari siswa..."><select id="siswaSel" class="form-select"><option value="">— Semua siswa —</option></select>
                    </div>
                @elseif($guruJenis === 'wali_kelas')
                    <div class="col-12 col-md-3">
                        <label class="form-label">Siswa</label>
                        <input type="text" class="form-control form-control-sm mb-1" id="siswa-search" placeholder="Cari siswa...">
                        <select id="siswaSel" class="form-select">
                            <option value="">— Semua siswa kelas Anda —</option>
                            @foreach(($studentOptions ?? collect()) as $s)
                                <option value="{{ $s['id'] }}">{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-primary" id="btnLoad">Muat</button>
                </div>
            </div>
            <hr>
            <canvas id="emosiChart" height="120"></canvas>
            <div class="small text-muted mt-2" id="meta"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    const $ = (s)=>document.querySelector(s);
    const periodSel = $('#period');
    const fromEl = $('#from');
    const toEl = $('#to');
    const kelasSel = $('#kelasSel');
    const siswaSel = $('#siswaSel');
    const kelasSearch = $('#kelas-search');
    const siswaSearch = $('#siswa-search');
    const btn = $('#btnLoad');
    const meta = $('#meta');

    let chart;
    function render(labels, values){
        const ctx = document.getElementById('emosiChart').getContext('2d');
        if (chart) chart.destroy();
    // Emoji scale: 10=🤩 Rad, 9=😍 In Love, 8=😎 Chill, 7=😊 Good, 5=😐 Meh/😴 Tired, 4=� Stressed, 3=� Bad, 2=� Overwhelmed, 1=� Awful
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
        const lerp = (a,b,t)=>a+(b-a)*t;
        const scoreToColor = (s)=>{
            const clamp = Math.max(1, Math.min(10, s||5));
            const t = (clamp-1)/9;
            const stops = [ {r:239,g:68,b:68}, {r:245,g:158,b:11}, {r:22,g:163,b:74} ];
            const s1 = t<0.5 ? stops[0] : stops[1];
            const s2 = t<0.5 ? stops[1] : stops[2];
            const tt = t<0.5 ? (t/0.5) : ((t-0.5)/0.5);
            const r = Math.round(lerp(s1.r,s2.r,tt));
            const g = Math.round(lerp(s1.g,s2.g,tt));
            const b = Math.round(lerp(s1.b,s2.b,tt));
            return `rgb(${r}, ${g}, ${b})`;
        };
        chart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label: 'Rata-rata Emosi', data: values, pointBackgroundColor: values.map(scoreToColor), pointBorderColor: values.map(scoreToColor), borderColor: values.length? scoreToColor(values.reduce((a,b)=>a+b,0)/values.length) : '#94a3b8', backgroundColor:'rgba(148,163,184,.15)', tension: .25, fill: true }] },
            options: {
                scales: { y: { suggestedMin: 1, suggestedMax: 10, ticks: { stepSize: 1, callback:(v)=>scoreEmoji(v) } } },
                plugins: { tooltip: { callbacks:{ label:(ctx)=>` ${scoreEmoji(ctx.parsed.y)}  avg ${ctx.parsed.y}` } } },
                segment: { borderColor: ctx => scoreToColor(ctx.p1.parsed.y) }
            }
        });
    }

    async function load(){
        const params = new URLSearchParams();
        const period = periodSel.value || 'daily';
        params.set('period', period);
        if (fromEl.value) params.set('from', fromEl.value);
        if (toEl.value) params.set('to', toEl.value);
        if (kelasSel && kelasSel.value) params.set('kelas_id', kelasSel.value);
        if (siswaSel && siswaSel.value) params.set('siswa_kelas_id', siswaSel.value);
        const res = await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`, { headers:{ 'Accept':'application/json' } });
        const data = await res.json();
        render(data.labels, (data.datasets?.[0]?.data) || []);
        meta.textContent = `Periode: ${data.period} | Rentang: ${data.from} s/d ${data.to} | Sampel total: ${(data.datasets?.[1]?.data||[]).reduce((a,b)=>a+b,0)}`;
        // Fill date inputs with defaults if empty
        if (!fromEl.value) fromEl.value = data.from;
        if (!toEl.value) toEl.value = data.to;
    }

    btn.addEventListener('click', load);
    periodSel.addEventListener('change', ()=>{ fromEl.value=''; toEl.value=''; load(); });

    // Simple client-side filter for selects
    function filterSelect(selectEl, q){
        if (!selectEl) return;
        const lower = (q||'').toLowerCase();
        Array.from(selectEl.options).forEach(opt=>{
            if (!opt.value) return; // keep placeholder
            const show = opt.text.toLowerCase().includes(lower);
            opt.hidden = !show;
        });
    }
    kelasSearch?.addEventListener('input', (e)=> filterSelect(kelasSel, e.target.value));
    siswaSearch?.addEventListener('input', (e)=> filterSelect(siswaSel, e.target.value));

    // When kelas changes (BK), fetch its students
    kelasSel?.addEventListener('change', async ()=>{
        if (!kelasSel.value || !siswaSel) { load(); return; }
        const url = `{{ route('guru.tren_emosi.siswa') }}?kelas_id=${encodeURIComponent(kelasSel.value)}`;
        const res = await fetch(url, { headers:{ 'Accept':'application/json' } });
        const data = await res.json();
        const opts = (data.items||[]).map(it=> `<option value="${it.id}">${it.label}</option>`).join('');
        siswaSel.innerHTML = `<option value="">— Semua siswa —</option>` + opts;
        load();
    });

    load();
})();
</script>
@endpush
