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
            /* Sidebar becomes an overlay panel */
            aside.sidebar{ position: fixed; top:0; left:0; height:100vh; width:80%; max-width:320px; transform: translateX(-100%); transition: transform .3s ease; z-index:1050; }
            aside.sidebar.is-open{ transform: translateX(0); }
            /* Hide sidebar column footprint when closed */
            aside.sidebar:not(.is-open){ display: none; }
            /* Hide default bootstrap topbar collapse to avoid duplicate menus on mobile */
            #topbar{ display: none !important; }
        }
    </style>
    @stack('head')
</head>

<body>
    {{-- Topbar --}}
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ route('admin.dashboard') }}">RASAYA Admin</a>
            {{-- Mobile hamburger to open sidebar --}}
            <button class="burger d-md-none" id="sidebarToggle" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
            {{-- Removed default navbar toggler to avoid duplicate with hamburger on smaller screens --}}
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
            {{-- Backdrop for mobile --}}
            <div id="sidebarBackdrop" class="d-md-none" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1040"></div>
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
        // Wait for Bootstrap to be loaded by Vite before running toast scripts
        document.addEventListener('DOMContentLoaded', function() {
            // Simple toast helper using Bootstrap 5
            window.rasayaToast = function(type, title, messages){
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
            };

            // Show session flashes and validation errors as toasts
            @if(session('success'))
                rasayaToast('success', 'Berhasil', @json(session('success')));
            @endif
            @if(session('error'))
                rasayaToast('danger', 'Terjadi kesalahan', @json(session('error')));
            @endif
            @if($errors->any())
                rasayaToast('danger', 'Gagal menyimpan', @json($errors->all()));
            @endif
        });
    </script>
    <script>
        // Defensive modal toggler to avoid data-api errors when target is missing
        // or when Bootstrap is loaded in module mode. Intercepts click and shows modal safely.
        document.addEventListener('click', function(e){
            const trigger = e.target.closest('[data-bs-toggle="modal"]');
            if (!trigger) return;
            const sel = trigger.getAttribute('data-bs-target') || trigger.getAttribute('data-target');
            if (!sel) return;
            const target = document.querySelector(sel);
            if (!target) {
                // Prevent Bootstrap data-api from throwing on undefined target
                e.preventDefault();
                e.stopImmediatePropagation();
                console.warn('Modal target not found:', sel);
                return;
            }
            // Use programmatic API to open, bypassing data-api
            e.preventDefault();
            e.stopImmediatePropagation();
            try {
                // Remember last trigger so listeners can use it when relatedTarget is missing
                window.rasayaLastModalTrigger = trigger;
                const Modal = window.bootstrap && window.bootstrap.Modal;
                if (Modal && typeof Modal.getOrCreateInstance === 'function') {
                    Modal.getOrCreateInstance(target).show();
                } else if (Modal) {
                    new Modal(target).show();
                }
            } catch (err) {
                console.error('Failed to open modal:', err);
            }
        }, true);
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
            // close when clicking a link inside sidebar
            aside.addEventListener('click', (e)=>{
                const a = e.target.closest('a.nav-link');
                if (a) close();
            });
            // Reset on resize to desktop
            window.addEventListener('resize', ()=>{
                if (window.innerWidth >= 768) { close(); }
            });
        })();
    </script>
</body>

</html>
