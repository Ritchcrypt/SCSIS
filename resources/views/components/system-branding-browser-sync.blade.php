@php
    $browserBrandingSetting = null;
    $browserSystemName = 'TabangNow';
    $browserLogoUrl = null;

    $browserSettingsTable = (new \App\Models\SystemSetting())->getTable();

    if (\Illuminate\Support\Facades\Schema::hasTable($browserSettingsTable)) {
        $browserBrandingSetting = \App\Models\SystemSetting::query()->first();
    }

    if ($browserBrandingSetting) {
        $configuredBrowserName = trim((string) $browserBrandingSetting->system_name);

        if ($configuredBrowserName !== '') {
            $browserSystemName = $configuredBrowserName;
        }

        if (
            $browserBrandingSetting->system_logo_path
            && \Illuminate\Support\Facades\Storage::disk('public')
                ->exists($browserBrandingSetting->system_logo_path)
            && \Illuminate\Support\Facades\Route::has('system-branding.logo')
        ) {
            $browserLogoUrl = route('system-branding.logo')
                . '?v='
                . optional($browserBrandingSetting->updated_at)->timestamp;
        }
    }
@endphp

<script>
    (() => {
        const systemName = @json($browserSystemName);
        const logoUrl = @json($browserLogoUrl);

        if (systemName) {
            document.title = systemName;
        }

        if (!logoUrl) {
            return;
        }

        const iconLinks = Array.from(
            document.querySelectorAll(
                'link[rel="icon"], link[rel="shortcut icon"]'
            )
        );

        if (iconLinks.length === 0) {
            const icon = document.createElement('link');
            icon.rel = 'icon';
            document.head.appendChild(icon);
            iconLinks.push(icon);
        }

        iconLinks.forEach((link) => {
            link.href = logoUrl;
            link.type = 'image/png';
        });
    })();
</script>
