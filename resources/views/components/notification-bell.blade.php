@php
    $notificationBell = app(\App\Services\NotificationBellService::class)->forUser(auth()->user());

    $unreadNotificationCount = (int) ($notificationBell['unread_count'] ?? 0);
    $latestUnreadNotifications = $notificationBell['notifications'] ?? collect();
    $notificationFallbackUrl = $notificationBell['fallback_url'] ?? '#';
@endphp

<details class="relative z-[110]">
    <summary class="relative inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-200"
             title="Notifications"
             aria-label="Notifications">
        <span class="text-xl">🔔</span>

        @if ($unreadNotificationCount > 0)
            <span class="absolute -right-2 -top-2 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
            </span>
        @endif
    </summary>

    <div id="notificationBackdrop"
         class="fixed bottom-0 left-72 right-0 top-16 z-[100] bg-slate-950/20 backdrop-blur-[1px] transition-[left] duration-300 ease-in-out"
         onclick="this.closest('details').removeAttribute('open')"
         aria-hidden="true">
    </div>

    <div class="fixed right-8 top-[4.5rem] z-[120] flex w-96 max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/10">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-bold text-slate-900">
                    Unread Notifications
                </p>

                <p class="text-xs text-slate-500">
                    Latest updates for your account
                </p>
            </div>

            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                {{ $unreadNotificationCount }}
            </span>
        </div>

        <div class="max-h-80 divide-y divide-slate-200 overflow-y-scroll overscroll-contain pr-1"
             style="scrollbar-gutter: stable;">
            @forelse ($latestUnreadNotifications as $notification)
                @php
                    $notificationId = $notification['id'] ?? null;
                    $typeLabel = $notification['type_label'] ?? 'Notification';
                    $notificationMessage = $notification['message'] ?? 'No notification message provided.';
                    $notificationAge = $notification['age'] ?? 'No date';
                    $fallbackUrl = $notification['fallback_url'] ?? $notificationFallbackUrl;
                    $canOpenNotification = (bool) ($notification['openable'] ?? false);
                @endphp

                @if ($canOpenNotification && $notificationId)
                    <form method="POST"
                          action="{{ route('notifications.open', $notificationId) }}"
                          class="m-0">
                        @csrf

                        <button type="submit"
                                class="block w-full px-4 py-3 text-left transition hover:bg-slate-50">
                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">
                                {{ $typeLabel }}
                            </span>

                            <p class="mt-2 text-xs leading-5 text-slate-600">
                                {{ $notificationMessage }}
                            </p>

                            <div class="mt-2 flex items-center justify-between gap-3">
                                <p class="text-[11px] text-slate-400">
                                    {{ $notificationAge }}
                                </p>

                                <span class="text-[11px] font-bold text-blue-700">
                                    Open →
                                </span>
                            </div>
                        </button>
                    </form>
                @else
                    <a href="{{ $fallbackUrl }}"
                       class="block px-4 py-3 transition hover:bg-slate-50">
                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">
                            {{ $typeLabel }}
                        </span>

                        <p class="mt-2 text-xs leading-5 text-slate-600">
                            {{ $notificationMessage }}
                        </p>

                        <div class="mt-2 flex items-center justify-between gap-3">
                            <p class="text-[11px] text-slate-400">
                                {{ $notificationAge }}
                            </p>

                            <span class="text-[11px] font-bold text-blue-700">
                                Open →
                            </span>
                        </div>
                    </a>
                @endif
            @empty
                <div class="px-4 py-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl">
                        🔔
                    </div>

                    <p class="mt-3 text-sm font-bold text-slate-900">
                        No unread notifications.
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        New updates will appear here.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</details>

@include('components.distress-signal-nav')
@include('components.system-branding-browser-sync')
