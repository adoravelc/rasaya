{{-- resources/views/roles/guru_bk/slot_konseling.blade.php --}}
@extends('layouts.guru')

@section('title', 'Slot Konseling (BK)')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Slot Konseling — BK</h4>
            <button class="btn btn-primary" id="btnPublish">+ Generate Slot Massal</button>
        </div>

        {{-- Filter --}}
        <div class="card mb-3">
            <div class="card-body row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Dari</label>
                    <input type="date" class="form-control" id="fFrom">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai</label>
                    <input type="date" class="form-control" id="fTo">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select id="fStatus" class="form-select">
                        <option value="">(Semua)</option>
                        <option value="published">published</option>
                        <option value="canceled">canceled</option>
                        <option value="archived">archived</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label d-block">&nbsp;</label>
                    <button class="btn btn-outline-secondary me-2" id="btnClear">Reset</button>
                    <button class="btn btn-success" id="btnFilter">Filter</button>
                </div>
            </div>
        </div>

        {{-- List --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Kuota</th>
                            <th>Booked</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Memuat data…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div id="meta" class="small text-muted">—</div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-secondary" id="prev">Prev</button>
                    <button class="btn btn-sm btn-outline-secondary" id="next">Next</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Publish Massal --}}
    <div class="modal fade" id="publishModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="publishForm">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Slot Massal</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" name="date_start" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" class="form-control" name="date_end" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hari (1=Senin … 7=Minggu)</label>
                            <div class="d-flex gap-2 flex-wrap">
                                @for ($i = 1; $i <= 7; $i++)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $i }}"
                                            id="d{{ $i }}" name="days[]">
                                        <label class="form-check-label"
                                            for="d{{ $i }}">{{ $i }}</label>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Jam Mulai</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jam Selesai</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Interval (menit)</label>
                            <select class="form-select" name="interval" required>
                                @foreach ([15, 20, 30, 45, 60] as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Durasi (menit)</label>
                            <select class="form-select" name="durasi" required>
                                @foreach ([15, 20, 30, 45, 60] as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Kapasitas</label>
                            <input type="number" class="form-control" name="capacity" min="1" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lokasi</label>
                            <input type="text" class="form-control" name="lokasi" placeholder="Ruang BK / Link">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Catatan</label>
                            <input type="text" class="form-control" name="notes" maxlength="255">
                        </div>

                        <div class="col-12">
                            <small class="text-muted">Zona waktu sistem: <strong>Asia/Makassar</strong>. Slot tersimpan di
                                DB sebagai UTC.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('scripts')
    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        // BASE route untuk WEB (bukan /api)
        const base = '/guru/bk/slots';

        const routes = {
            list: (p = '') => `${base}${p}`,
            publish: () => `${base}/publish`,
            cancel: (id) => `${base}/${id}/cancel`,
            archive: (id) => `${base}/${id}/archive`,
        };

        let modal, pageUrl = '';

        document.addEventListener('DOMContentLoaded', () => {
            modal = new bootstrap.Modal(document.getElementById('publishModal'));
            document.getElementById('btnPublish').addEventListener('click', () => modal.show());
            document.getElementById('publishForm').addEventListener('submit', onPublish);
            document.getElementById('btnFilter').addEventListener('click', load);
            document.getElementById('btnClear').addEventListener('click', () => {
                ['fFrom', 'fTo', 'fStatus'].forEach(id => document.getElementById(id).value = '');
                load();
            });
            document.getElementById('prev').addEventListener('click', () => paginate('prev'));
            document.getElementById('next').addEventListener('click', () => paginate('next'));
            load();
        });

        function q() {
            const p = new URLSearchParams();
            const from = document.getElementById('fFrom').value;
            const to = document.getElementById('fTo').value;
            const st = document.getElementById('fStatus').value;
            if (from) p.set('from', from);
            if (to) p.set('to', to);
            if (st) p.set('status', st);
            return p.toString() ? `?${p.toString()}` : '';
        }

        async function load(url) {
            const tbody = document.getElementById('rows');
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Memuat…</td></tr>`;
            const res = await fetch(url || routes.list(q()), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const j = await res.json();
            const data = j.data ?? j; // paginate or array
            pageUrl = j.path ? j.path : routes.list(); // for paginate
            renderRows(data);
            renderMeta(j);
        }

        function renderMeta(j) {
            const meta = document.getElementById('meta');
            if (!j || !j.total) {
                meta.textContent = '—';
                return;
            }
            meta.textContent = `Menampilkan ${j.from}–${j.to} dari ${j.total}`;
            document.getElementById('prev').disabled = !j.prev_page_url;
            document.getElementById('next').disabled = !j.next_page_url;
            document.getElementById('prev').dataset.url = j.prev_page_url || '';
            document.getElementById('next').dataset.url = j.next_page_url || '';
        }

        function paginate(which) {
            const btn = document.getElementById(which);
            const url = btn.dataset.url;
            if (url) load(url.replace('/api/slots', base)); // jaga-jaga bila ikut path api
        }

        function toLocal(dt) {
            // render start_at / end_at (UTC) ke lokal Asia/Makassar
            const d = new Date(dt);
            return d.toLocaleString('id-ID', {
                timeZone: 'Asia/Makassar',
                hour: '2-digit',
                minute: '2-digit',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        }

        function hhmm(dt) {
            const d = new Date(dt);
            return d.toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Makassar',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }

        function renderRows(items) {
            const tbody = document.getElementById('rows');
            if (!items || items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada data.</td></tr>`;
                return;
            }
            tbody.innerHTML = items.map(s => {
                const tgl = s.tanggal ?? (s.start_at ? toLocal(s.start_at).slice(0, 10) : '-');
                const times = s.start_at && s.end_at ? `${hhmm(s.start_at)}–${hhmm(s.end_at)}` : '-';
                const badge = s.status === 'published' ? 'info' : (s.status === 'archived' ? 'dark' : 'secondary');
                return `<tr>
      <td>${tgl}</td>
      <td>${times}</td>
      <td>${s.capacity ?? '-'}</td>
      <td>${s.booked_count ?? 0}</td>
      <td>${s.lokasi ?? '-'}</td>
      <td><span class="badge bg-${badge}">${s.status}</span></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
          ${s.status==='published' ? `<button class="btn btn-outline-danger" onclick="doCancel(${s.id})">Cancel</button>` : ''}
          ${s.status!=='archived' ? `<button class="btn btn-outline-dark" onclick="doArchive(${s.id})">Archive</button>` : ''}
        </div>
      </td>
    </tr>`;
            }).join('');
        }

        function getCheckedDays() {
            const days = [];
            for (let i = 1; i <= 7; i++) {
                const checkbox = document.getElementById(`d${i}`);
                if (checkbox && checkbox.checked) {
                    days.push(checkbox.value);
                }
            }
            return days;
        }

        async function onPublish(e) {
            e.preventDefault();
            const f = e.target;

            // sebelum fetch
            const to24 = s => {
                // kirimkan nilai <input type="time"> langsung kalau ada
                // kalau pakai teks "01:00 PM", convert cepat:
                const m = s.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
                if (!m) return s;
                let [_, hh, mm, ap] = m;
                hh = parseInt(hh, 10) % 12 + (/PM/i.test(ap) ? 12 : 0);
                return `${String(hh).padStart(2,'0')}:${mm}`;
            };

            const body = {
                date_start: f.date_start.value, // "2025-10-16"
                date_end: f.date_end.value, // "2025-10-16"
                days: getCheckedDays().map(n => parseInt(n, 10)), // [4]
                start_time: to24(f.start_time.value), // "13:00"
                end_time: to24(f.end_time.value), // "14:10"
                interval: parseInt(f.interval.value, 10),
                durasi: parseInt(f.durasi.value, 10),
                capacity: parseInt(f.capacity.value || '1', 10),
                lokasi: f.lokasi.value || null,
                notes: f.notes.value || null,
            };

            // pastikan ada minimal satu hari
            if (!body.days.length) {
                alert('Pilih minimal satu hari.');
                return;
            }

            const fd = new FormData();
            for (const [key, value] of Object.entries(body)) {
                if (key === 'days') {
                    for (const day of value) {
                        fd.append('days[]', day);
                    }
                } else if (value !== null) {
                    fd.append(key, value);
                }
            }

            const res = await fetch(routes.publish(), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: fd
            });

            if (!res.ok) {
                const err = await res.json().catch(() => ({
                    message: 'Gagal generate'
                }));
                alert(err.message || 'Gagal generate');
                return;
            }
            const data = await res.json();
            modal.hide();
            alert(`Berhasil generate ${data.generated} slot`);
            load();
        }

        async function doCancel(id) {
            if (!confirm('Batalkan slot ini (beserta booking aktif)?')) return;
            const res = await fetch(routes.cancel(id), {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) load();
            else alert('Gagal membatalkan.');
        }
        async function doArchive(id) {
            if (!confirm('Arsipkan slot ini?')) return;
            const res = await fetch(routes.archive(id), {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) load();
            else alert('Gagal mengarsipkan.');
        }
    </script>
@endpush
