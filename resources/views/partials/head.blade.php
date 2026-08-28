@php
    $headBrandingSetting = null;
    $headSystemName = 'TabangNow';
    $headBrandingLogoUrl = null;

    $headSettingsTable = (new \App\Models\SystemSetting())->getTable();

    if (\Illuminate\Support\Facades\Schema::hasTable($headSettingsTable)) {
        $headBrandingSetting = \App\Models\SystemSetting::query()->first();
    }

    if ($headBrandingSetting) {
        $configuredName = trim((string) $headBrandingSetting->system_name);

        if ($configuredName !== '') {
            $headSystemName = $configuredName;
        }

        if (
            $headBrandingSetting->system_logo_path
            && \Illuminate\Support\Facades\Storage::disk('public')
                ->exists($headBrandingSetting->system_logo_path)
            && \Illuminate\Support\Facades\Route::has('system-branding.logo')
        ) {
            $headBrandingLogoUrl = route('system-branding.logo')
                . '?v='
                . optional($headBrandingSetting->updated_at)->timestamp;
        }
    }
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{ $title ?? $headSystemName }}</title>

@if ($headBrandingLogoUrl)
    <link
        rel="icon"
        type="image/png"
        sizes="32x32"
        href="{{ $headBrandingLogoUrl }}"
    >

    <link
        rel="shortcut icon"
        type="image/png"
        href="{{ $headBrandingLogoUrl }}"
    >
@endif

<link rel="preconnect" href="https://fonts.bunny.net">

<link
    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900"
    rel="stylesheet"
/>

@vite([
    'resources/css/app.css',
    'resources/js/app.js',
])

@fluxAppearance