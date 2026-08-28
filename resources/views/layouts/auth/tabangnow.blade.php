@php
    $setting = null;
    $logoUrl = null;

    $settingsTable = (new \App\Models\SystemSetting())->getTable();

    if (\Illuminate\Support\Facades\Schema::hasTable($settingsTable)) {
        $setting = \App\Models\SystemSetting::query()->first();
    }

    $systemName = trim((string) ($setting?->system_name ?: 'TabangNow'));
    $systemSubtitle = trim((string) ($setting?->system_subtitle ?: 'Dao, Capiz'));

    /*
    |--------------------------------------------------------------------------
    | Public auth logo
    |--------------------------------------------------------------------------
    | Authentication pages use the administrator-managed global system logo.
    | There is deliberately no bundled legacy-logo fallback.
    */
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

                </div>
            </section>

            <aside class="tn-auth-visual-panel">
                <x-auth.response-scene :system-name="$systemName" />
            </aside>
        </main>

        @fluxScripts
    </body>
</html>