<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin')</title>
    @vite(['resources/js/app.js'])
    <style>
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #e5e7eb;
            background: var(--ras-broken-white, #f8f9fa);
        }

        .sidebar .nav-link.active {
            background: #e9ecef;
            font-weight: 600;
        }
    </style>
    @stack('head')
</head>

<body>
    {{-- Topbar --}}
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ route('admin.dashboard') }}">RASAYA Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="topbar">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item me-3 text-muted small" id="now-wita"></li>
                    <li class="nav-item me-3 text-muted small">
                        Halo, <strong>{{ auth()->user()->name }}</strong>
                        <span class="d-none d-sm-inline">({{ auth()->user()->identifier }})</span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}"
                            onsubmit="return confirm('Yakin ingin logout?')">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            {{-- Sidebar (role-aware) --}}
            <x-app-sidebar :role="auth()->user()->role ?? 'guest'" />

            {{-- Content --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                {{-- Header halaman (opsional) --}}
                @hasSection('page-header')
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        @yield('page-header')
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
    <script>
        // Simple toast helper using Bootstrap 5
        function rasayaToast(type, title, messages){
            const containerId = 'toast-container';
            let container = document.getElementById(containerId);
            if(!container){
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = 1080;
                document.body.appendChild(container);
            }
            const color = {
                success:'text-bg-success',
                danger:'text-bg-danger',
                warning:'text-bg-warning',
                info:'text-bg-info',
                primary:'text-bg-primary'
            }[type] || 'text-bg-primary';

            const el = document.createElement('div');
            el.className = `toast align-items-center border-0 ${color}`;
            el.setAttribute('role','alert');
            el.setAttribute('aria-live','assertive');
            el.setAttribute('aria-atomic','true');
            el.innerHTML = `
                <div class="d-flex">
                  <div class="toast-body">
                    <div class="fw-semibold mb-1">${title || ''}</div>
                    ${Array.isArray(messages) ? '<ul class="mb-0 ps-3">'+messages.map(m=>`<li>${m}</li>`).join('')+'</ul>' : (messages||'')}
                  </div>
                  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>`;
            container.appendChild(el);
            const toast = new bootstrap.Toast(el, { delay: 5000 });
            toast.show();
        }

        // Show session flashes and validation errors as toasts
        (function(){
            @if(session('success'))
                rasayaToast('success', 'Berhasil', @json(session('success')));
            @endif
            @if(session('error'))
                rasayaToast('danger', 'Terjadi kesalahan', @json(session('error')));
            @endif
            @if($errors->any())
                rasayaToast('danger', 'Gagal menyimpan', @json($errors->all()));
            @endif
        })();
    </script>
    <script>
        function fmtWita(d){
            return d.toLocaleString('id-ID', { timeZone:'Asia/Makassar', weekday:'long', year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit', hour12:false}).replace(',', '');
        }
        (function(){
            const el = document.getElementById('now-wita');
            if(!el) return;
            const tick = ()=>{ el.textContent = fmtWita(new Date()) + ' WITA'; };
            tick();
            setInterval(tick, 30000);
        })();
    </script>
</body>

</html>
