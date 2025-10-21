<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Guru — RASAYA')</title>
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

        /* Mobile hamburger and sidebar animations */
        .burger {
            width: 40px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background .2s ease, border-color .2s ease;
        }
        .burger:hover { background: #e2e8f0; border-color: #94a3b8; }
        .burger span { display:block; width:20px; height:2px; background:#374151; margin:3px 0; transition: transform .25s ease, opacity .2s ease; }
        .burger.open span:nth-child(1){ transform: translateY(5px) rotate(45deg); }
        .burger.open span:nth-child(2){ opacity: 0; }
        .burger.open span:nth-child(3){ transform: translateY(-5px) rotate(-45deg); }

        @media (max-width: 767.98px){
            aside.sidebar{ position: fixed; top:0; left:0; height:100vh; width:80%; max-width:320px; transform: translateX(-100%); transition: transform .3s ease; z-index:1050; }
            aside.sidebar.is-open{ transform: translateX(0); }
            aside.sidebar:not(.is-open){ display: none; }
            .navbar .navbar-toggler{ display: none !important; }
            #topbar{ display: none !important; }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ url('/guru') }}">RASAYA Guru</a>
            {{-- Mobile hamburger to open sidebar --}}
            <button class="burger d-md-none" id="sidebarToggle" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
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
            {{-- Sidebar (role guru) --}}
            {{-- Backdrop for mobile --}}
            <div id="sidebarBackdrop" class="d-md-none" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1040"></div>
            <x-app-sidebar :role="'guru'" />
            {{-- Content --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('modals')
    @stack('scripts')
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
        // Update any .now-wita in mobile sidebar
        (function(){
            const els = document.querySelectorAll('.now-wita');
            if(!els.length) return;
            const tick = ()=>{
                const t = fmtWita(new Date()) + ' WITA';
                els.forEach(e=> e.textContent = t);
            };
            tick();
            setInterval(tick, 30000);
        })();
        // Sidebar mobile toggling (3-dots)
        (function(){
            const toggle = document.getElementById('sidebarToggle');
            const backdrop = document.getElementById('sidebarBackdrop');
            const aside = document.querySelector('aside.sidebar');
            if (!toggle || !aside) return;
            const open = ()=>{
                aside.classList.add('is-open');
                toggle.classList.add('open');
                toggle.setAttribute('aria-expanded','true');
                backdrop && (backdrop.style.display = 'block');
                document.body.style.overflow = 'hidden';
            };
            const close = ()=>{
                aside.classList.remove('is-open');
                toggle.classList.remove('open');
                toggle.setAttribute('aria-expanded','false');
                backdrop && (backdrop.style.display = 'none');
                document.body.style.overflow = '';
            };
            toggle.addEventListener('click', (e)=>{
                e.preventDefault();
                if (aside.classList.contains('is-open')) close(); else open();
            });
            backdrop && backdrop.addEventListener('click', close);
            aside.addEventListener('click', (e)=>{
                const a = e.target.closest('a.nav-link');
                if (a) close();
            });
            window.addEventListener('resize', ()=>{
                if (window.innerWidth >= 768) { close(); }
            });
        })();
    </script>
</body>

</html>
