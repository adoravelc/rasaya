<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'RASAYA') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- fallback style ringan (tanpa build) --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
        <style>
            :root {
                --bg: #f8fafc;
                --card: #ffffff;
                --text: #0f172a;
                --muted: #475569;
                --brand: #0ea5e9;
                --brand-2: #0369a1
            }

            * {
                box-sizing: border-box
            }

            html,
            body {
                height: 100%
            }

            body {
                margin: 0;
                font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
                background: var(--bg);
                color: var(--text)
            }

            .wrap {
                min-height: 100%;
                display: grid;
                place-items: center;
                padding: 24px
            }

            .card {
                width: 100%;
                max-width: 880px;
                background: var(--card);
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 6px 20px rgba(2, 6, 23, .06);
                overflow: hidden
            }

            .grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0
            }

            @media (min-width:960px) {
                .grid {
                    grid-template-columns: 1.1fr .9fr
                }
            }

            .hero {
                padding: 40px 32px
            }

            .eyebrow {
                display: inline-block;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: .08em;
                text-transform: uppercase;
                color: var(--brand-2);
                background: #e0f2fe;
                border: 1px solid #bae6fd;
                padding: .25rem .5rem;
                border-radius: 999px
            }

            h1 {
                font-size: clamp(28px, 4vw, 40px);
                line-height: 1.2;
                margin: .75rem 0 .5rem 0
            }

            p {
                margin: 0;
                color: var(--muted)
            }

            .cta {
                margin-top: 22px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap
            }

            .btn {
                appearance: none;
                cursor: pointer;
                border-radius: 10px;
                padding: .7rem 1rem;
                font-weight: 600;
                border: 1px solid #cbd5e1;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: .5rem
            }

            .btn-primary {
                background: var(--brand);
                border-color: var(--brand);
                color: #fff
            }

            .btn-primary:hover {
                background: #0284c7;
                border-color: #0284c7
            }

            .btn-ghost:hover {
                border-color: #94a3b8
            }

            .right {
                background: linear-gradient(135deg, #e0f2fe 0%, #fff 60%);
                border-left: 1px solid #e2e8f0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center
            }

            .illus {
                width: min(460px, 90%);
                aspect-ratio: 16/10;
                border-radius: 14px;
                border: 1px dashed #94a3b8;
                background:
                    radial-gradient(120px 80px at 18% 30%, #bae6fd 0%, transparent 70%),
                    radial-gradient(160px 100px at 78% 70%, #fecaca 0%, transparent 70%),
                    #fff;
                box-shadow: inset 0 0 0 1px #e2e8f0
            }

            .brand {
                display: inline-flex;
                gap: .6rem;
                align-items: center
            }

            .brand i {
                display: inline-block;
                width: 10px;
                height: 10px;
                background: var(--brand);
                border-radius: 3px;
                box-shadow: 14px 0 0 var(--brand-2), 28px 0 0 #22c55e
            }

            footer {
                margin-top: 18px;
                font-size: 12px;
                color: #64748b
            }
        </style>
    @endif
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="grid">
                <section class="hero">
                    <div class="brand"><i></i><span class="fw-600">{{ config('app.name', 'RASAYA') }}</span></div>
                    <span class="eyebrow">Selamat datang</span>
                    <h1>Platform bimbingan & konseling sekolah</h1>
                    <p>Mood tracker, observasi guru, dan slot konseling terjadwal — terintegrasi web & mobile.</p>

                    <div class="cta">
                        @auth
                            <a class="btn btn-primary" href="{{ route('dashboard') }}">Buka Dashboard</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="btn btn-ghost" type="submit">Keluar</button>
                            </form>
                        @else
                            <a class="btn btn-primary" href="{{ route('login') }}">Masuk</a>
                        @endauth
                    </div>

                    <footer>
                        © {{ date('Y') }} {{ config('app.name', 'RASAYA') }} · Asia/Makassar
                    </footer>
                </section>

                <aside class="right">
                    <div class="illus" aria-hidden="true"></div>
                </aside>
            </div>
        </div>
    </div>
</body>

</html>
