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

    /*
    |--------------------------------------------------------------------------
    | Temporary Authentication Logo
    |--------------------------------------------------------------------------
    | The official barangay seal remains stored but is not displayed publicly
    | until the final branding has been approved.
    */

    $logoUrl = asset('images/tabangnow-default-logo.svg');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')

    <meta name="theme-color" content="#0f2f5f">
</head>

<body class="tn-auth-body antialiased">
    <main class="tn-auth-shell">
        <section class="tn-auth-form-panel">
            <div class="tn-auth-form-inner">
                <x-auth.brand
                    :system-name="$systemName"
                    :system-subtitle="$systemSubtitle"
                    :logo-url="$logoUrl"
                />

                <div class="tn-auth-form-card">
                    {{ $slot }}
                </div>

                <footer class="tn-auth-footer">
                    <span>Secure community access</span>
                    <span aria-hidden="true">•</span>
                    <span>{{ $systemSubtitle }}</span>
                </footer>
            </div>
        </section>

        <aside class="tn-auth-visual-panel">
            <x-auth.response-scene
                :system-name="$systemName"
            />
        </aside>
    </main>

    @fluxScripts
</body>
</html>