<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin')</title>
    @vite(['resources/js/app.js'])
    <style>
        :root {
            --admin-navy: rgba(30, 58, 138, 0.08);
            --admin-navy-solid: #1e3a8a;
            --admin-navy-light: rgba(59, 130, 246, 0.12);
            --admin-pink: rgba(252, 231, 243, 0.5);
            --admin-pink-dark: #ec4899;
        }
        .sidebar {
            min-height: 100vh;
            border-right: none;
            background: rgba(248, 250, 252, 0.8);
            backdrop-filter: blur(20px);
            color: #334155;
            box-shadow: 4px 0 16px rgba(0, 0, 0, 0.06);
        }
        .sidebar .nav-link {
            color: #475569;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            background: rgba(30, 58, 138, 0.08);
            color: #1e3a8a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .sidebar .nav-link.active {
            background: #1e3a8a;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        }
        .sidebar .text-muted {
            color: #94a3b8 !important;
        }
        .card-admin-accent {
            border-left: 4px solid var(--admin-pink-dark);
        }
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12) !important;
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
    <nav class="navbar navbar-expand navbar-light bg-white border-bottom sticky-top" style="box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <div class="container-fluid px-4">
            {{-- Mobile hamburger --}}
            <button class="burger d-md-none me-3" id="sidebarToggle" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
            
            {{-- Date & Greeting --}}
            <div class="d-none d-md-block me-4">
                <div class="small" style="color: #94a3b8; font-size: 0.75rem;" id="current-datetime">Loading...</div>
                <div>
                    <span style="color: #64748b;">Hello, </span>
                    <strong style="color: #1e293b;">{{ auth()->user()->name }}</strong>
                    <span class="ms-1" style="color: #94a3b8;">👋</span>
                </div>
            </div>

            {{-- Search bar --}}
            <div class="flex-grow-1" style="max-width: 400px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0" style="border-color: #e2e8f0;">
                        <i class="bi bi-search" style="color: #94a3b8;"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search anything" style="border-color: #e2e8f0;" readonly>
                </div>
            </div>

            {{-- Right side: Notifications & Profile --}}
            <ul class="navbar-nav ms-auto align-items-center">
                {{-- Notifications --}}
                <li class="nav-item dropdown me-3">
                    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" style="color: #64748b;">
                        <i class="bi bi-bell" style="font-size: 1.2rem;"></i>
                        @if(isset($unreadCount) && $unreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="px-3 py-2 border-bottom">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0 fw-semibold" style="color: #1e293b;">Notifikasi</h6>
                                @if(isset($unreadCount) && $unreadCount > 0)
                                <small class="text-muted">{{ $unreadCount }} baru</small>
                                @endif
                            </div>
                        </li>
                        @if(isset($unreadNotifications) && $unreadNotifications->count() > 0)
                            @foreach($unreadNotifications as $notif)
                            <li>
                                <a class="dropdown-item py-2" href="{{ $notif->link ?? '#' }}" style="white-space: normal;">
                                    <div class="d-flex gap-2">
                                        <div class="flex-shrink-0">
                                            @if(str_contains($notif->type, 'password'))
                                            <i class="bi bi-key-fill" style="color: #1e3a8a;"></i>
                                            @else
                                            <i class="bi bi-info-circle-fill" style="color: #1e3a8a;"></i>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold small" style="color: #1e293b;">{{ $notif->title }}</div>
                                            <div class="small text-muted">{{ Str::limit($notif->message, 60) }}</div>
                                            <div class="small" style="color: #94a3b8; font-size: 0.7rem;">{{ $notif->created_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            @endforeach
                        @else
                            <li><a class="dropdown-item small text-muted text-center py-3" href="#">Tidak ada notifikasi</a></li>
                        @endif
                    </ul>
                </li>

                {{-- Messages --}}
                <li class="nav-item me-3">
                    <a class="nav-link" href="#" style="color: #64748b;">
                        <i class="bi bi-chat-dots" style="font-size: 1.2rem;"></i>
                    </a>
                </li>

                {{-- Profile Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(30, 58, 138, 0.1);">
                            <i class="bi bi-person-fill" style="color: #1e3a8a; font-size: 1.1rem;"></i>
                        </div>
                        <div class="d-none d-lg-block text-start">
                            <div class="small fw-semibold" style="color: #1e293b; line-height: 1.2;">{{ auth()->user()->name }}</div>
                            <div style="font-size: 0.7rem; color: #94a3b8; line-height: 1;">Admin</div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header">{{ auth()->user()->identifier }}</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" onsubmit="return confirm('Yakin ingin logout?')">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
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
        
        // Update current datetime in topbar
        (function(){
            const el = document.getElementById('current-datetime');
            if(!el) return;
            const tick = ()=>{ 
                const d = new Date();
                const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                const day = days[d.getDay()];
                const date = d.getDate();
                const month = months[d.getMonth()];
                const year = d.getFullYear();
                const hours = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                el.textContent = `${day}, ${date} ${month} ${year} ${hours}.${minutes} WITA`;
            };
            tick();
            setInterval(tick, 30000);
        })();
        
        // Search functionality
        (function(){
            const searchInput = document.querySelector('input[placeholder="Search anything"]');
            if(!searchInput) return;
            searchInput.removeAttribute('readonly');
            
            // All menu items from sidebar
            const menuItems = [];
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                const text = link.textContent.trim();
                const href = link.getAttribute('href');
                if(text && href) {
                    menuItems.push({ text, href, element: link });
                }
            });
            
            let resultsDropdown = null;
            
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.toLowerCase().trim();
                
                // Remove old dropdown
                if(resultsDropdown) {
                    resultsDropdown.remove();
                    resultsDropdown = null;
                }
                
                if(query.length < 2) return;
                
                // Filter menu items
                const matches = menuItems.filter(item => 
                    item.text.toLowerCase().includes(query)
                ).slice(0, 8);
                
                if(matches.length === 0) return;
                
                // Create dropdown
                resultsDropdown = document.createElement('div');
                resultsDropdown.className = 'position-absolute bg-white border rounded shadow-sm';
                resultsDropdown.style.cssText = 'top: 100%; left: 0; right: 0; z-index: 1000; max-height: 300px; overflow-y: auto; margin-top: 4px;';
                
                matches.forEach(match => {
                    const item = document.createElement('a');
                    item.href = match.href;
                    item.className = 'dropdown-item py-2';
                    item.textContent = match.text;
                    item.style.cursor = 'pointer';
                    resultsDropdown.appendChild(item);
                });
                
                searchInput.closest('.input-group').parentElement.style.position = 'relative';
                searchInput.closest('.input-group').parentElement.appendChild(resultsDropdown);
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if(resultsDropdown && !searchInput.closest('.input-group').parentElement.contains(e.target)) {
                    resultsDropdown.remove();
                    resultsDropdown = null;
                }
            });
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
