@extends('layouts.guru')

@section('title', 'Dashboard Wali Kelas')

@section('content')
<div class="container-fluid">
	<div class="row">
		<main class="col-12 col-md-9 col-lg-10 p-4">
			@php
				$kelasWk = \App\Models\Kelas::with(['tahunAjaran'])
					->where('wali_guru_id', auth()->id())
					->latest('tahun_ajaran_id')
					->first();
			@endphp
			<div class="d-flex align-items-center justify-content-between mb-3">
				<div>
					<h2 class="h4 mb-1">Dashboard Wali Kelas</h2>
					<div class="text-muted">Halo, {{ auth()->user()->name }}</div>
					<div class="mt-2">
						<div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 border bg-light-subtle">
							<div class="small text-muted">Identitas Wali Kelas</div>
							@if($kelasWk)
								<span class="badge text-bg-primary">{{ $kelasWk->label }}</span>
								<span class="badge text-bg-secondary">TA {{ $kelasWk->tahunAjaran->nama ?? '-' }}</span>
							@else
								<span class="text-muted small">Belum terdaftar pada tahun ajaran aktif</span>
							@endif
						</div>
					</div>
				</div>
			</div>

			@if(!auth()->user()->password_changed_at && auth()->user()->initial_password)
			<div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
				<span class="fw-semibold">Segera ubah password anda.</span>
				<span class="small">Gunakan token awal sebagai Password lama pada form ubah password.</span>
				<a href="{{ route('guru.profile.index', ['pwd' => 1]) }}" class="btn btn-sm btn-outline-dark ms-auto">Ubah Sekarang</a>
			</div>
			@endif
			<div class="row g-3">
				<div class="col-12">
					<div class="card shadow-sm h-100">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<div class="text-muted small mb-1">Statistik</div>
									<div class="fs-5 fw-semibold">Tren Emosi Siswa (Kelas Anda)</div>
								</div>
								<a href="{{ route('guru.tren_emosi.index') }}" class="btn btn-outline-primary btn-sm">Lihat semua</a>
							</div>
							<div class="d-flex align-items-center gap-2 mt-3">
								<select id="wk-period" class="form-select form-select-sm" style="max-width:180px;">
									<option value="daily" selected>Harian</option>
									<option value="weekly">Mingguan</option>
									<option value="monthly">Bulanan</option>
								</select>
								<small id="wk-meta" class="text-muted"></small>
							</div>
							<canvas id="wk-emosi-chart" height="140" class="mt-3"></canvas>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<div class="text-muted small mb-1">Observasi</div>
									<div class="fs-5 fw-semibold">Input Guru</div>
								</div>
								<span class="display-6">📝</span>
							</div>
							<a href="{{ route('guru.observasi.index') }}" class="btn btn-primary btn-sm mt-3 stretched-link">Buka</a>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<div class="text-muted small mb-1">Kelas</div>
									<div class="fs-5 fw-semibold">Rekap Siswa</div>
								</div>
								<span class="display-6">📋</span>
							</div>
							<a href="#" class="btn btn-outline-secondary btn-sm mt-3 disabled" aria-disabled="true">Segera</a>
						</div>
					</div>
				</div>
			</div>

			@if(($attentionList ?? collect())->isNotEmpty())
			<div class="card mt-4 shadow-sm">
				<div class="card-header bg-white fw-semibold">Siswa Perlu Perhatian (Kelas Anda)</div>
				<div class="list-group list-group-flush">
					@foreach($attentionList as $a)
						<a href="{{ route('guru.analisis.show', $a->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
							<div>
								<div class="fw-semibold">{{ optional($a->siswaKelas->siswa->user)->name }}</div>
								<div class="small text-muted">
									{{ optional($a->siswaKelas->kelas)->label }} — TA {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
								</div>
							</div>
							<div class="text-end small text-muted">
								<div>Analisis: {{ optional($a->created_at)->diffForHumans() }}</div>
							</div>
						</a>
					@endforeach
				</div>
			</div>
			@endif

			<div class="card mt-4 shadow-sm">
				<div class="card-header bg-white fw-semibold">Hari Ini</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-md-4">
							<div class="p-3 border rounded-3 h-100">
								<div class="small text-muted">Tanggal</div>
								<div class="fs-5">{{ now('Asia/Makassar')->translatedFormat('l, d M Y') }}</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="p-3 border rounded-3 h-100">
								<div class="small text-muted">Akun</div>
								<div class="fs-5">{{ auth()->user()->email }}</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="p-3 border rounded-3 h-100">
								<div class="small text-muted">Peran</div>
								<div class="fs-5">Wali Kelas</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
	const sel = document.getElementById('wk-period');
	const meta = document.getElementById('wk-meta');
	let chart;
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
	const todayLocal = ()=>{ const d=new Date(); d.setMinutes(d.getMinutes()-d.getTimezoneOffset()); return d.toISOString().slice(0,10); };
	async function load(){
		// Default WK dashboard: bar per siswa (hari ini)
		const params = new URLSearchParams({ period: sel.value || 'daily', from: todayLocal(), to: todayLocal(), group: 'siswa' });
		const res = await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`, { headers:{ 'Accept':'application/json' } });
		const data = await res.json();
		const ys = data.datasets?.[0]?.data || [];
		const labels = data.labels || [];
		const colors = data.colors || ys.map(scoreToColor);
		const ctx = document.getElementById('wk-emosi-chart').getContext('2d');
		if (chart) chart.destroy();
		chart = new Chart(ctx, {
			type:'bar',
			data:{ labels, datasets:[{ label:'Rata-rata Emosi', data:ys, backgroundColor: colors, borderWidth:0, borderRadius:6 }] },
			options:{ indexAxis:'y', scales:{ x:{ suggestedMin:1, suggestedMax:10, ticks:{ stepSize:1, callback:(v)=>scoreEmoji(v) } }, y:{ ticks:{ autoSkip:false } } }, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx)=>` ${scoreEmoji(ctx.parsed.x)}  avg ${ctx.parsed.x}` } } } }
		});
		meta.textContent = `Periode: ${data.period} — ${data.from} s/d ${data.to}`;
	}
	sel?.addEventListener('change', load);
	load();
})();
</script>
@endpush
