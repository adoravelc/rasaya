<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Guru — RASAYA')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/app_icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app_icon.png') }}">
    @vite(['resources/js/app.js'])
    <style>
        :root {
            --guru-pink: #fce7f3;
            --guru-pink-dark: #ec4899;
            --guru-navy: #1e3a8a;
            --guru-navy-light: #3b82f6;
        }
        .sidebar {
            min-height: 100vh;
            border-right: none;
            background: rgba(253, 242, 248, 0.8);
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
            background: rgba(236, 72, 153, 0.08);
            color: var(--guru-pink-dark);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .sidebar .nav-link.active {
            background: var(--guru-navy);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        }
        .card-guru-accent {
            border-left: 4px solid var(--guru-navy);
        }
        .card-guru-pink {
            border-top: 3px solid var(--guru-pink-dark);
            background: linear-gradient(135deg, #fff 0%, var(--guru-pink) 100%);
        }
        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
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
        /* Desktop: keep sidebar static (does not scroll with main content) */
        @media (min-width: 768px){
            aside.sidebar{ position: sticky; top: 0; height: 100vh; overflow-y: auto; }
            /* Ensure main content scrolls independently and layout stays aligned */
            main.col-md-9, main.col-lg-10{ min-height: 100vh; }
        }
    </style>
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
                <div class="small" style="color: #94a3b8; font-size: 0.75rem;" id="current-datetime">Memuat...</div>
                <div>
                    <span style="color: #64748b;">Halo, </span>
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
                    <input type="text" class="form-control border-start-0" placeholder="Cari apa saja" style="border-color: #e2e8f0;" readonly>
                </div>
            </div>

            {{-- Right side: Notifications & Profile --}}
            <ul class="navbar-nav ms-auto align-items-center">
                @if(session('guest_mode'))
                <li class="nav-item me-3">
                    <span class="badge text-bg-warning">Guest Read-Only</span>
                </li>
                @endif

                {{-- Notifications --}}
                <li class="nav-item dropdown me-3">
                    <a class="nav-link position-relative p-2" href="#" role="button" data-bs-toggle="dropdown" style="color: #64748b; transition: all 0.2s;" onmouseover="this.style.color='#ec4899'" onmouseout="this.style.color='#64748b'">
                        <i class="bi bi-bell-fill" style="font-size: 1.3rem;"></i>
                        @if(isset($unreadCount) && $unreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; border: 2px solid white;">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
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
                            <div class="mt-2 d-flex justify-content-end">
                                <form method="post" action="{{ route('notifications.read_all') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: .65rem;">
                                        <i class="bi bi-check2-all me-1"></i> Tandai semua dibaca
                                    </button>
                                </form>
                            </div>
                        </li>
                        @if(isset($unreadNotifications) && $unreadNotifications->count() > 0)
                            @foreach($unreadNotifications as $notif)
                            <li>
                                <a class="dropdown-item py-2" href="{{ $notif->link ?? '#' }}" style="white-space: normal;">
                                    <div class="d-flex gap-2">
                                        <div class="flex-shrink-0">
                                            @if(str_contains($notif->type, 'konseling'))
                                            <i class="bi bi-calendar-check-fill" style="color: #ec4899;"></i>
                                            @elseif(str_contains($notif->type, 'mood'))
                                            <i class="bi bi-emoji-frown-fill" style="color: #f59e0b;"></i>
                                            @else
                                            <i class="bi bi-info-circle-fill" style="color: #ec4899;"></i>
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
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(236, 72, 153, 0.1);">
                            <i class="bi bi-person-fill" style="color: #ec4899; font-size: 1.1rem;"></i>
                        </div>
                        <div class="d-none d-lg-block text-start">
                            <div class="small fw-semibold" style="color: #1e293b; line-height: 1.2;">{{ auth()->user()->name }}</div>
                            <div style="font-size: 0.7rem; color: #94a3b8; line-height: 1;">{{ auth()->user()->guru->jenis === 'bk' ? 'Guru BK' : 'Wali Kelas' }}</div>
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
            {{-- Sidebar (role guru) --}}
            {{-- Backdrop for mobile --}}
            <div id="sidebarBackdrop" class="d-md-none" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1040"></div>
            <x-app-sidebar :role="'guru'" />
            {{-- Content --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                @if(session('guest_mode'))
                    <div class="alert alert-warning mb-3" role="alert">
                        Anda berada di mode guest demo. Perubahan hanya disimpan sementara di sesi ini dan tidak masuk database utama.
                    </div>
                @endif
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
        
        // Update current datetime in topbar
        (function(){
            const el = document.getElementById('current-datetime');
            if(!el) return;
            const tick = ()=>{ 
                const d = new Date();
                const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                
                // Convert to WITA timezone (Asia/Makassar)
                const witaDate = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Makassar' }));
                
                const day = days[witaDate.getDay()];
                const date = witaDate.getDate();
                const month = months[witaDate.getMonth()];
                const year = witaDate.getFullYear();
                const hours = String(witaDate.getHours()).padStart(2, '0');
                const minutes = String(witaDate.getMinutes()).padStart(2, '0');
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
