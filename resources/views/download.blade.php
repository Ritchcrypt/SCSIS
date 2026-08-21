<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Download TabangNow"
            description="Official Android application for Dao, Capiz"
        />

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
            <div class="flex items-center justify-between gap-4">
                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                    Version
                </span>
                <span>v{{ $version }}</span>
            </div>

            @if ($sizeLabel)
                <div class="mt-2 flex items-center justify-between gap-4">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">
                        APK size
                    </span>
                    <span>{{ $sizeLabel }}</span>
                </div>
            @endif

            @if ($sha256 !== '')
                <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">
                        Verified SHA-256
                    </p>

                    <p class="mt-1 break-all font-mono text-xs">
                        {{ $sha256 }}
                    </p>
                </div>
            @endif
        </div>

        <flux:button
            variant="primary"
            href="{{ route('download.apk') }}"
            class="w-full"
        >
            Download TabangNow for Android
        </flux:button>

        <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
            <p>
                Download and install TabangNow only from this official page.
            </p>

            <p>
                Android may ask you to allow installation from your browser
                or file manager when installing the APK.
            </p>
        </div>

        <div class="text-center text-sm">
            <x-text-link href="{{ route('login') }}">
                Back to login
            </x-text-link>
        </div>
    </div>
</x-layouts.auth>