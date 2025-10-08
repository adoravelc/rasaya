{{-- resources/views/roles/admin/kelas/index.blade.php --}}
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kelola Kelas — Admin</title>
    <link rel="stylesheet" href="https://unpkg.com/mvp.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: #0004
        }

        .modal>div {
            background: #fff;
            padding: 16px;
            margin: 8% auto;
            max-width: 560px
        }

        .actions button {
            margin-right: .5rem
        }
    </style>
</head>

<body>
    <main>
        <h2>Data Kelas</h2>

        <form method="get">
            <label>Tahun ajaran:
                <select name="tahun_ajaran_id" onchange="this.form.submit()">
                    @foreach ($tahunAjarans as $ta)
                        <option value="{{ $ta->id }}" {{ $activeTa == $ta->id ? 'selected' : '' }}>
                            {{ $ta->nama }} {{ $ta->is_active ? '(aktif)' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
        </form>

        <p>
            <button onclick="openCreate()" role="button">+ Tambah Kelas</button>
        </p>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Label</th>
                    <th>Tingkat</th>
                    <th>Penjurusan</th>
                    <th>Rombel</th>
                    <th>Wali Guru</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="rows">
                @foreach ($kelas as $i => $k)
                    <tr data-id="{{ $k->id }}">
                        <td>{{ $kelas->firstItem() + $i }}</td>
                        <td class="td-label">{{ $k->label }}</td>
                        <td class="td-tingkat">{{ $k->tingkat }}</td>
                        <td class="td-jur">{{ $k->penjurusan ?? '-' }}</td>
                        <td class="td-rombel">{{ $k->rombel }}</td>
                        <td class="td-wali">{{ $k->waliGuru->name ?? '-' }}</td>
                        <td class="actions">
                            <button onclick="openEdit({{ $k->id }})">Edit</button>
                            <button onclick="doDelete({{ $k->id }})">Hapus</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $kelas->withQueryString()->links() }}

        <h3>Terhapus (soft delete)</h3>
        <ul id="trashed">
            @foreach ($trashed as $t)
                <li>
                    {{ $t->label }}
                    <button onclick="restore({{ $t->id }})">Pulihkan</button>
                    <button onclick="forceDel({{ $t->id }})">Hapus Permanen</button>
                </li>
            @endforeach
        </ul>
    </main>

    {{-- Modal --}}
    <div id="modal" class="modal">
        <div>
            <h3 id="m-title">Form Kelas</h3>
            <form id="m-form" onsubmit="submitForm(event)">
                <input type="hidden" id="m-id">

                <label>Tahun Ajaran
                    <select id="m-ta" required>
                        @foreach ($tahunAjarans as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                </label>

                <label>Tingkat
                    <select id="m-tingkat" required>
                        <option>X</option>
                        <option>XI</option>
                        <option>XII</option>
                    </select>
                </label>

                <label>Penjurusan (opsional)
                    <select id="m-penjurusan">
                        <option value="">—</option>
                        <option>IPA</option>
                        <option>IPS</option>
                    </select>
                </label>

                <label>Rombel (nomor)
                    <input id="m-rombel" type="number" min="1" required>
                </label>

                <label>Wali Guru (User ID)
                    <input id="m-wali" type="number" placeholder="opsional">
                </label>

                <div id="m-error" style="color:red; white-space:pre-wrap"></div>
                <button type="submit">Simpan</button>
                <button type="button" onclick="closeModal()">Batal</button>
            </form>
        </div>
    </div>

    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/kelas';

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Kelas';
            document.getElementById('m-id').value = '';
            document.getElementById('m-ta').value = '{{ $activeTa }}';
            document.getElementById('m-tingkat').value = 'X';
            document.getElementById('m-penjurusan').value = '';
            document.getElementById('m-rombel').value = '';
            document.getElementById('m-wali').value = '';
            document.getElementById('m-error').innerText = '';
            document.getElementById('modal').style.display = 'block';
        }

        function openEdit(id) {
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            document.getElementById('m-title').innerText = 'Edit Kelas';
            document.getElementById('m-id').value = id;
            document.getElementById('m-ta').value = '{{ $activeTa }}';
            document.getElementById('m-tingkat').value = tr.querySelector('.td-tingkat').innerText.trim();
            document.getElementById('m-penjurusan').value =
                (tr.querySelector('.td-jur').innerText.trim() === '-' ? '' :
                    tr.querySelector('.td-jur').innerText.trim());
            document.getElementById('m-rombel').value = tr.querySelector('.td-rombel').innerText.trim();
            document.getElementById('m-wali').value = '';
            document.getElementById('m-error').innerText = '';
            document.getElementById('modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;

            const payload = {
                tahun_ajaran_id: document.getElementById('m-ta').value,
                tingkat: document.getElementById('m-tingkat').value,
                penjurusan: document.getElementById('m-penjurusan').value || null,
                rombel: Number(document.getElementById('m-rombel').value),
                wali_guru_id: document.getElementById('m-wali').value || null,
            };

            const opts = {
                method: id ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            };

            const url = id ? `${base}/${id}` : base;
            const res = await fetch(url, opts);
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                document.getElementById('m-error').innerText =
                    JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            location.reload(); // nanti bisa diganti update row tanpa reload
        }

        async function doDelete(id) {
            if (!confirm('Hapus kelas?')) return;
            const res = await fetch(`${base}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }

        async function restore(id) {
            const res = await fetch(`${base}/${id}/restore`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }

        async function forceDel(id) {
            if (!confirm('Hapus permanen?')) return;
            const res = await fetch(`${base}/${id}/force`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }
    </script>
</body>

</html>
