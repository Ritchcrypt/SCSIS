<x-layouts.auth>
    <div class="flex flex-col gap-5 text-zinc-800">
        <x-auth-header
            title="Get TabangNow"
            description="Official Android app and website access for Dao, Capiz"
        />

        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Android mobile application</p>
            <p class="mt-2 text-sm text-blue-950">
                Download directly from this official TabangNow website. No Play Store account needed.
            </p>

            @if ($downloadAvailable)
                <a href="{{ route('download.apk') }}"
                   class="mt-4 flex min-h-12 w-full items-center justify-center rounded-xl bg-blue-800 px-5 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800"
                   aria-label="Download TabangNow Android APK">
                    Download for Android
                </a>
                <p class="mt-2 text-xs text-blue-900">
                    Version v{{ $version }}@if ($sizeLabel) &middot; {{ $sizeLabel }}@endif
                </p>
            @else
                <p class="mt-4 rounded-xl border border-blue-200 bg-white px-3 py-3 text-sm font-medium text-blue-900"
                   role="status">
                    The verified Android download is being prepared. Please check back later.
                </p>
            @endif
        </div>

        <div class="space-y-3">
            <div class="text-center">
                <h2 class="text-base font-semibold text-zinc-900">Two ways to connect</h2>
                <p class="mt-1 text-xs text-zinc-600">Scan with your phone camera, or tap the link beneath each QR code.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-sm">
                    <p class="mb-2 text-sm font-semibold text-zinc-900">1. Download app</p>
                    <a href="{{ route('download') }}" class="block rounded-lg bg-white p-1 focus-visible:outline-2 focus-visible:outline-blue-700"
                       aria-label="Open official TabangNow Android download page">
                        <img src="{{ asset('images/qr/tabangnow-mobile-download.png') }}"
                             width="250" height="250"
                             alt="QR code for the official TabangNow mobile download page"
                             class="mx-auto aspect-square w-full max-w-40 object-contain"
                             loading="eager">
                    </a>
                    <a class="mt-2 inline-block break-words text-xs font-semibold text-blue-800 underline underline-offset-2"
                       href="{{ route('download') }}">Mobile download page</a>
                </div>

                <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-3 text-center shadow-sm">
                    <p class="mb-2 text-sm font-semibold text-zinc-900">2. Open website</p>
                    <a href="{{ route('home') }}" class="block rounded-lg bg-white p-1 focus-visible:outline-2 focus-visible:outline-blue-700"
                       aria-label="Open official TabangNow website">
                        <img src="{{ asset('images/qr/tabangnow-website.png') }}"
                             width="250" height="250"
                             alt="QR code for the official TabangNow website"
                             class="mx-auto aspect-square w-full max-w-40 object-contain"
                             loading="eager">
                    </a>
                    <a class="mt-2 inline-block break-words text-xs font-semibold text-blue-800 underline underline-offset-2"
                       href="{{ route('home') }}">TabangNow website</a>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-3 text-xs leading-5 text-zinc-700">
            <p class="font-semibold text-zinc-900">Installing on Android</p>
            <p class="mt-1">Tap Download for Android, open the downloaded APK, and follow your phone's installation prompts. Only allow installation from the browser or file manager you used.</p>
            <p class="mt-2">For security, download TabangNow only from this official website. You can disable the browser's install permission afterward.</p>
            @if ($downloadAvailable && $sha256 !== '')
                <details class="mt-2">
                    <summary class="cursor-pointer font-medium text-zinc-800">File integrity (SHA-256)</summary>
                    <code class="mt-2 block break-all text-[11px] text-zinc-600">{{ $sha256 }}</code>
                </details>
            @endif
        </div>

        <div class="text-center text-sm">
            <x-text-link href="{{ route('login') }}">Go to login</x-text-link>
        </div>
    </div>
</x-layouts.auth>
