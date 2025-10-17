{{-- resources/views/roles/guru_bk/slot_konseling.blade.php --}}
@extends('layouts.guru')

@section('title', 'Slot Konseling (BK)')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Slot Konseling — BK</h4>
            <button class="btn btn-primary" id="btnPublish">+ Generate Slot</button>
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
                    <label class="form-label">Ketersediaan</label>
                    <select id="fStatus" class="form-select">
                        <option value="">(Semua)</option>
                        <option value="available">available</option>
                        <option value="booked">booked</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label d-block">&nbsp;</label>
                    <button class="btn btn-outline-secondary me-2" id="btnClear">Reset</button>
                    <button id="btn-filter" class="btn btn-success">Filter</button>
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
                            <th>Booked</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Memuat data…</td>
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
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" name="date_start" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" class="form-control" name="date_end" required>
                        </div>

                        <!-- Hidden input for days - default to all days (1..7 ISO: Mon..Sun) -->
                        <input type="hidden" name="days[]" value="1">
                        <input type="hidden" name="days[]" value="2">
                        <input type="hidden" name="days[]" value="3">
                        <input type="hidden" name="days[]" value="4">
                        <input type="hidden" name="days[]" value="5">
                        <input type="hidden" name="days[]" value="6">
                        <input type="hidden" name="days[]" value="7">

                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Jam Mulai</label>
                                    <input type="time" class="form-control" name="start_time" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jam Selesai</label>
                                    <input type="time" class="form-control" name="end_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Interval (menit)</label>
                            <select class="form-select" name="interval" required>
                                @foreach ([15, 20, 30, 45, 60] as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Durasi (menit)</label>
                            <select class="form-select" name="durasi" required>
                                @foreach ([15, 20, 30, 45, 60] as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lokasi</label>
                            <input type="text" class="form-control" name="lokasi" placeholder="Ruang BK / Link">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Catatan</label>
                            <input type="text" class="form-control" name="notes" maxlength="255">
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

            // Fix the ID to match the HTML
            document.getElementById('btn-filter').addEventListener('click', (e) => {
                e.preventDefault();
                load(); // This is fine when calling without arguments
            });

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
            if (st) {
                // map availability to API parameters
                if (st === 'available') p.set('availability', 'available');
                else if (st === 'booked') p.set('availability', 'booked');
                else p.set('status', st);
            }
            return p.toString() ? `?${p.toString()}` : '';
        }

        async function load(url) {
            const tbody = document.getElementById('rows');
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Memuat…</td></tr>`;

            try {
                const res = await fetch(url || routes.list(q()), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }

                const j = await res.json();
                console.log('Response data:', j); // Debug to see what's coming back

                let data = j.data ?? j; // paginate or array
                // Client-side availability filter fallback
                const avail = document.getElementById('fStatus').value;
                if (avail === 'available') {
                    data = data.filter(s => s.status === 'published' && Number(s.booked_count ?? 0) === 0);
                } else if (avail === 'booked') {
                    data = data.filter(s => Number(s.booked_count ?? 0) > 0);
                }
                console.log('Data to render:', data); // Debug to see what's being passed to renderRows

                pageUrl = j.path ? j.path : routes.list(); // for paginate
                renderRows(data);
                renderMeta(j);
            } catch (error) {
                console.error('Error loading data:', error);
                tbody.innerHTML =
                    `<tr><td colspan="7" class="text-center py-4 text-danger">Error loading data: ${error.message}</td></tr>`;
            }
        }

        function wireUpPagination(paginated) {
            const container = document.querySelector('#pagination');
            container.querySelectorAll('a.page-link').forEach(a => {
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    const href = a.getAttribute('href'); // string URL
                    if (href) load(href); // <— kirim string URL
                });
            });
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
            // Format date as "Kamis, 16 Oktober 2025"
            const d = new Date(dt);
            return d.toLocaleDateString('id-ID', {
                timeZone: 'Asia/Makassar',
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
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
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada data.</td></tr>`;
                return;
            }
            tbody.innerHTML = items.map(s => {
                // If we have a tanggal property, format it. Otherwise, get it from start_at
                let dateToFormat = s.tanggal ? new Date(s.tanggal) : (s.start_at ? new Date(s.start_at) : null);
                const tgl = dateToFormat ? toLocal(dateToFormat) : '-';

                                                const times = s.start_at && s.end_at ? `${hhmm(s.start_at)}–${hhmm(s.end_at)}` : '-';
                                const isAvailable = (s.status === 'published') && (Number(s.booked_count ?? 0) === 0);
                                const badge = s.status === 'archived' ? 'dark' : (isAvailable ? 'success' : 'secondary');
                return `<tr>
<td>${tgl}</td>
<td>${times}</td>
                <td>${s.booked_count ?? 0}</td>
<td>${s.lokasi ?? '-'}</td>
<td><span class="badge bg-${badge}">${isAvailable ? 'available' : (s.status ?? '-')}</span></td>
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
            // Now just returns all weekdays (1-5) as default
            return [1, 2, 3, 4, 5];
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
                days: [1, 2, 3, 4, 5, 6, 7], // All days: Mon..Sun (ISO 1..7)
                start_time: to24(f.start_time.value), // "13:00"
                end_time: to24(f.end_time.value), // "14:10"
                interval: parseInt(f.interval.value, 10),
                durasi: parseInt(f.durasi.value, 10),
                lokasi: f.lokasi.value || null,
                notes: f.notes.value || null,
            };

            // No need to check days anymore
            // if (!body.days.length) {
            //     alert('Pilih minimal satu hari.');
            //     return;
            // }

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

            // Rest of the function remains the same
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
            const diag = `Generated: ${data.generated}\nExisting: ${data.existing}\nAttempted: ${data.attempted}\nSkipped (window): ${data.skipped}\nDays: ${Array.isArray(data.days_iso)?data.days_iso.join(','):data.days_iso}`;
            alert(`Berhasil generate ${data.generated} slot\n\n${diag}`);
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
