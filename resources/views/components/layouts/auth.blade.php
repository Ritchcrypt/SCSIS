@php
    $setting = null;

    $settingsTable = (new \App\Models\SystemSetting())->getTable();

    if (\Illuminate\Support\Facades\Schema::hasTable($settingsTable)) {
        $setting = \App\Models\SystemSetting::query()->first();
    }

    $systemName = trim(
        (string) ($setting?->system_name ?: 'TabangNow')
    );

    $systemSubtitle = trim(
        (string) ($setting?->system_subtitle ?: 'Dao, Capiz')
    );

    $logoUrl = null;

    if (
        $setting?->system_logo_path
        && \Illuminate\Support\Facades\Storage::disk('public')
            ->exists($setting->system_logo_path)
        && \Illuminate\Support\Facades\Route::has('system-branding.logo')
    ) {
        $logoUrl = route('system-branding.logo')
            . '?v='
            . optional($setting->updated_at)->timestamp;
    }
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="light"
    data-theme="light"
    style="color-scheme: light;"
>
<head>
    @include('partials.head')

    <script>
        (() => {
            // TABANGNOW_PUBLIC_AUTH_LIGHT_PREPAINT: set light theme before first paint.
            const root = document.documentElement;

            root.classList.remove('dark');
            root.classList.add('light');
            root.dataset.theme = 'light';
            root.style.colorScheme = 'light';
        })();
    </script>

    <meta name="theme-color" content="#ffffff">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body.tn21-auth {
            min-height: 100vh;
            overflow-x: hidden;
            background: #beddf1;
            color: #102653;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .tn21-page {
            min-height: 100vh;
        }

        .tn21-hero {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 48px 24px;
        }

        .tn21-background {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            background: linear-gradient(165deg, #7cb8e7 0%, #b3d2e6 47%, #264968 100%);
        }

        .tn21-background::before {
            content: "";
            position: absolute;
            inset: -18px;
            background:
                linear-gradient(
                    rgba(255,255,255,.02),
                    rgba(17,50,81,.16)
                ),
                url('/images/auth/dao-login-landscape.svg')
                center center / cover no-repeat;

            filter: blur(7px) saturate(1.08);
            transform: scale(1.055);
        }

        .tn21-background::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(
                    90deg,
                    rgba(255,255,255,.14),
                    rgba(10,38,72,.08)
                );
        }

        .tn21-card {
            position: relative;
            z-index: 10;
            width: min(100%, 520px);
            padding: 30px 38px 31px;
            border: 1px solid rgba(255,255,255,.88);
            border-radius: 20px;
            background: rgba(255,255,255,.95);
            box-shadow:
                0 32px 80px rgba(3,27,64,.27),
                0 7px 24px rgba(3,27,64,.13);

            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .tn21-card-brand {
            display: flex;
            justify-content: center;
            margin-bottom: 6px;
        }

        .tn21-card-logo {
            width: 116px;
            height: 116px;
            object-fit: contain;
        }

        .tn21-card-logo-fallback {
            width: 118px;
            height: 118px;
            display: grid;
            place-items: center;
            border: 4px solid #123b7c;
            border-radius: 50%;
            color: #123b7c;
            background: white;
            font-weight: 800;
            text-align: center;
        }

        .tn21-card-branding {
            text-align: center;
            margin-bottom: 10px;
        }

        .tn21-card-name {
            color: #092c67;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 800;
            line-height: 1.14;
            letter-spacing: .025em;
        }

        .tn21-card-subtitle {
            margin-top: 4px;
            color: #244273;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .2em;
            text-transform: uppercase;
        }

        .tn21-card-gold-accent {
            width: 52px;
            height: 4px;
            margin: 13px auto 0;
            border-radius: 999px;
            background: #e9a51c;
        }

        /* Maintain readable authentication field labels. */
        .tn21-card label,
        .tn21-card [data-flux-label] {
            color: #254266 !important;
        }

        .tn21-login-title {
            margin: 0;
            color: #092b67;
            text-align: center;
            font-size: 37px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.03em;
        }

        .tn21-login-description {
            margin: 11px 0 25px;
            color: #5b6880;
            text-align: center;
            font-size: 14px;
        }

        .tn21-form {
            display: flex;
            flex-direction: column;
            gap: 17px;
        }

        .tn21-password-wrap {
            position: relative;
        }

        .tn21-forgot-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .tn21-link {
            color: #0b5ed7;
            font-size: 13px;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .tn21-remember {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #56647c;
            font-size: 13px;
            cursor: pointer;
        }

        .tn21-remember input {
            width: 16px;
            height: 16px;
            accent-color: #0b4c91;
        }

        .tn21-submit {
            width: 100%;
        }

        .tn21-submit button {
            width: 100% !important;
            min-height: 50px !important;
            border-radius: 12px !important;
            background:
                linear-gradient(
                    90deg,
                    #092f71,
                    #0753a5
                ) !important;
            color: #fff !important;
            font-size: 16px !important;
            font-weight: 700 !important;
        }

        .tn21-divider {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #53617a;
            font-size: 13px;
        }

        .tn21-divider::before,
        .tn21-divider::after {
            content: "";
            height: 1px;
            flex: 1;
            background: #b0bacb;
        }

        .tn21-signup {
            width: 100%;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #075ed1;
            border-radius: 12px;
            background: white;
            color: #075ed1;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            transition:
                background .15s ease,
                color .15s ease;
        }

        .tn21-signup:hover {
            background: #075ed1;
            color: white;
        }

        @media (max-width: 900px) {
            .tn21-hero {
                padding: 30px 18px;
            }
        }

        @media (max-width: 520px) {
            .tn21-hero {
                padding: 22px 14px;
            }

            .tn21-card {
                padding: 26px 22px;
                border-radius: 18px;
            }

            .tn21-card-logo {
                width: 100px;
                height: 100px;
            }

            .tn21-card-subtitle {
                font-size: 9px;
                letter-spacing: .13em;
            }

            .tn21-login-title {
                font-size: 31px;
            }
        }


        /* Only the public signup page uses the wider, landscape card. */
        .tn21-register-hero .tn21-card {
            width: min(100%, 1040px);
            padding: 30px 38px;
        }

        .tn21-register-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 26px;
        }

        .tn21-register-column {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 20px;
        }

        @media (min-width: 900px) {
            .tn21-register-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 32px;
            }
        }

        @media (max-width: 520px) {
            .tn21-register-hero .tn21-card {
                padding: 26px 20px;
            }
        }

/* CAPSTONE21_AUTH_INTERACTION_START */

/*
|--------------------------------------------------------------------------
| Authentication interaction / contrast upgrade
|--------------------------------------------------------------------------
*/

.tn21-card {
    border: 1px solid rgba(14, 58, 116, .14);
    background: rgba(255, 255, 255, .96);
    box-shadow:
        0 30px 75px rgba(5, 34, 73, .28),
        0 8px 26px rgba(5, 34, 73, .15);
}

/* Give auth form controls more separation from the white card. */
.tn21-card input:not([type="checkbox"]),
.tn21-card textarea,
.tn21-card select {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        background-color .18s ease,
        transform .12s ease;
}

.tn21-card input:not([type="checkbox"]):focus,
.tn21-card textarea:focus,
.tn21-card select:focus {
    background-color: #ffffff !important;
    border-color: #1767c0 !important;
    box-shadow:
        0 0 0 3px rgba(23, 103, 192, .14) !important;
}

/*
|--------------------------------------------------------------------------
| Submit buttons
|--------------------------------------------------------------------------
*/

.tn21-card form button[type="submit"] {
    min-height: 50px !important;
    border: 1px solid #0a458c !important;
    border-radius: 12px !important;

    background:
        linear-gradient(
            135deg,
            #0b3d80 0%,
            #0754a6 55%,
            #0873cf 100%
        ) !important;

    color: #ffffff !important;

    box-shadow:
        0 8px 18px rgba(6, 71, 146, .22);

    cursor: pointer;

    transition:
        transform .12s ease,
        box-shadow .18s ease,
        filter .18s ease !important;
}

.tn21-card form button[type="submit"]:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);

    box-shadow:
        0 11px 23px rgba(6, 71, 146, .30);
}

.tn21-card form button[type="submit"]:active {
    transform: translateY(1px) scale(.985);

    box-shadow:
        0 4px 10px rgba(6, 71, 146, .24);
}

.tn21-card form button[type="submit"]:focus-visible {
    outline: 3px solid rgba(36, 125, 220, .32);
    outline-offset: 3px;
}

/*
|--------------------------------------------------------------------------
| Signup page "Log in" link
|--------------------------------------------------------------------------
*/

.tn21-card a[href*="login"] {
    position: relative;

    color: #0756b5 !important;

    font-weight: 700 !important;
    text-decoration: none !important;

    transition:
        color .16s ease,
        transform .16s ease,
        text-shadow .16s ease;
}

.tn21-card a[href*="login"]::after {
    content: "";

    position: absolute;

    left: 0;
    right: 0;
    bottom: -3px;

    height: 2px;

    border-radius: 999px;

    background: #f5a900;

    transform: scaleX(0);
    transform-origin: center;

    transition: transform .18s ease;
}

.tn21-card a[href*="login"]:hover {
    color: #063d82 !important;

    text-shadow:
        0 1px 8px rgba(7, 86, 181, .12);
}

.tn21-card a[href*="login"]:hover::after {
    transform: scaleX(1);
}

.tn21-card a[href*="login"]:active {
    transform: scale(.95);
}

.tn21-card a[href*="login"]:focus-visible {
    outline: 3px solid rgba(7, 86, 181, .22);
    outline-offset: 5px;
    border-radius: 4px;
}

/*
|--------------------------------------------------------------------------
| Registration footer readability
|--------------------------------------------------------------------------
*/

.tn21-card .text-zinc-600,
.tn21-card .text-zinc-500,
.tn21-card .text-zinc-400 {
    color: #65728a !important;
}

/* CAPSTONE21_AUTH_INTERACTION_END */

</style>
</head>

<body class="tn21-auth antialiased">

<div class="tn21-page">



    <main class="tn21-hero{{ request()->routeIs('register') ? ' tn21-register-hero' : '' }}">

        <div
            class="tn21-background"
            aria-hidden="true"
        ></div>

        <section class="tn21-card">

            <div class="tn21-card-brand">

                @if ($logoUrl)

                    <img
                        src="{{ $logoUrl }}"
                        alt="{{ $systemName }} logo"
                        class="tn21-card-logo"
                    >

                @else

                    <div class="tn21-card-logo-fallback">
                        {{ $systemName }}
                    </div>

                @endif

            </div>

            @if (request()->routeIs('login'))
                <div class="tn21-card-branding">
                    <div class="tn21-card-name">{{ $systemName }}</div>
                    <div class="tn21-card-subtitle">{{ $systemSubtitle }}</div>
                    <div class="tn21-card-gold-accent" aria-hidden="true"></div>
                </div>
            @endif

            {{ $slot }}

        </section>

    </main>

</div>

@fluxScripts

</body>
</html>

