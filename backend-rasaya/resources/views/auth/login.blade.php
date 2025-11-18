<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — RASAYA</title>
    @vite(['resources/js/app.js'])
    <style>
        .auth-bg {
            min-height: 100vh;
            background: var(--ras-broken-white, #f7f7f2);
        }
        .brand-dot {
            width: .6rem; height: .6rem; border-radius: 50%; background: var(--ras-secondary, #0F6A49);
            display: inline-block; margin-left: .35rem;
        }
    </style>
    @stack('head')
    </head>

<body class="auth-bg d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5">
                <div class="text-center mb-4">
                    <div class="fw-bold" style="font-size:1.5rem;color:var(--ras-primary, #192653);">RASAYA<span class="brand-dot"></span></div>
                    <div class="text-muted small">Masuk untuk melanjutkan</div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <form method="POST" action="{{ url('/login') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="identifier" class="form-label">NIS/NUPTK</label>
                                <input id="identifier" type="text" name="identifier" value="{{ old('identifier') }}" class="form-control @error('identifier') is-invalid @enderror" autocomplete="username" required>
                                @error('identifier')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Kata Sandi</label>
                                <div class="input-group">
                                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan/Sembunyikan kata sandi">Tampilkan</button>
                                    @error('password')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="remember">Ingat saya</label>
                                </div>
                                <a class="small" href="{{ route('password.forgot') }}">Lupa kata sandi?</a>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Masuk</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center text-muted small mt-3">
                    © {{ date('Y') }} RASAYA
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const btn = document.getElementById('togglePassword');
            const pwd = document.getElementById('password');
            if (btn && pwd) {
                btn.addEventListener('click', function(){
                    const isPwd = pwd.getAttribute('type') === 'password';
                    pwd.setAttribute('type', isPwd ? 'text' : 'password');
                    btn.textContent = isPwd ? 'Sembunyikan' : 'Tampilkan';
                });
            }
        })();
    </script>
    @stack('scripts')
</body>

</html>
