@php
    // A single favicon source for public auth, authenticated layouts, and MFA.
    // Prefer the administrator-managed Dao logo; preserve the static icon only
    // when no uploaded logo exists on this environment's storage disk.
    $browserFaviconLogoUrl = $brandingLogoUrl ?? null;

    if (! $browserFaviconLogoUrl) {
        $faviconSettingsTable = (new \App\Models\SystemSetting())->getTable();

        if (\Illuminate\Support\Facades\Schema::hasTable($faviconSettingsTable)) {
            $faviconSetting = \App\Models\SystemSetting::query()->first();
            $faviconLogoPath = $faviconSetting?->system_logo_path;

            if (
                is_string($faviconLogoPath)
                && $faviconLogoPath !== ''
                && \Illuminate\Support\Facades\Storage::disk('public')->exists($faviconLogoPath)
                && \Illuminate\Support\Facades\Route::has('system-branding.logo')
            ) {
                $browserFaviconLogoUrl = route('system-branding.logo')
                    . '?v='
                    . optional($faviconSetting->updated_at)->timestamp;
            }
        }
    }

    $browserFaviconUrl = $browserFaviconLogoUrl
        ?: asset('tabangnow-tab-icon-v7.png') . '?v=13';
@endphp
<link rel="icon" href="{{ $browserFaviconUrl }}">
<link rel="shortcut icon" href="{{ $browserFaviconUrl }}">
