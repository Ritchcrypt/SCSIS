(() => {
    if (window.__tabangNowNotificationSoundInstalled) {
        return;
    }

    window.__tabangNowNotificationSoundInstalled = true;

    const pulseUrl = document
        .querySelector(
            'meta[name="notification-pulse-url"]'
        )
        ?.getAttribute('content');

    const userId = document
        .querySelector(
            'meta[name="notification-user-id"]'
        )
        ?.getAttribute('content');

    if (!pulseUrl || !userId) {
        return;
    }

    const POLL_INTERVAL_MS = 15000;

    const sessionCursorKey =
        `tabangnow.notification.cursor.${userId}`;

    const lastSoundedKey =
        `tabangnow.notification.last-sounded.${userId}`;

    let audioContext = null;

    let audioReady = false;

    let requestRunning = false;

    let pollTimer = null;

    /*
    |--------------------------------------------------------------------------
    | Browser audio permission
    |--------------------------------------------------------------------------
    |
    | Modern browsers normally require user interaction before allowing
    | websites to play audio.
    |
    | The first click, key press, or touch silently enables the notification
    | sound system.
    |
    */
    function unlockAudio() {
        if (audioReady) {
            return;
        }

        const AudioContextClass =
            window.AudioContext
            || window.webkitAudioContext;

        if (!AudioContextClass) {
            return;
        }

        try {
            if (!audioContext) {
                audioContext =
                    new AudioContextClass();
            }

            const resumePromise =
                audioContext.state === 'suspended'
                    ? audioContext.resume()
                    : Promise.resolve();

            Promise.resolve(resumePromise)
                .then(() => {
                    audioReady =
                        audioContext?.state
                        === 'running';
                })
                .catch(() => {
                    audioReady = false;
                });
        } catch (error) {
            console.warn(
                'Notification audio could not be enabled.',
                error
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Notification chime
    |--------------------------------------------------------------------------
    |
    | Generated locally with Web Audio.
    |
    | No third-party sound file is required and no copyrighted notification
    | sound is bundled into the project.
    |
    */
    function playNotificationSound() {
        if (
            !audioReady
            || !audioContext
            || audioContext.state !== 'running'
        ) {
            return;
        }

        try {
            const start =
                audioContext.currentTime;

            const gain =
                audioContext.createGain();

            gain.gain.setValueAtTime(
                0.0001,
                start
            );

            gain.gain.exponentialRampToValueAtTime(
                0.16,
                start + 0.02
            );

            gain.gain.exponentialRampToValueAtTime(
                0.0001,
                start + 0.55
            );

            gain.connect(
                audioContext.destination
            );

            const tones = [
                {
                    frequency: 880,
                    delay: 0,
                    duration: 0.22,
                },
                {
                    frequency: 1174.66,
                    delay: 0.18,
                    duration: 0.30,
                },
            ];

            tones.forEach((tone) => {
                const oscillator =
                    audioContext.createOscillator();

                oscillator.type = 'sine';

                oscillator.frequency.setValueAtTime(
                    tone.frequency,
                    start + tone.delay
                );

                oscillator.connect(gain);

                oscillator.start(
                    start + tone.delay
                );

                oscillator.stop(
                    start
                    + tone.delay
                    + tone.duration
                );
            });
        } catch (error) {
            console.warn(
                'Notification sound could not be played.',
                error
            );
        }
    }

    function readInteger(
        storage,
        key
    ) {
        try {
            const value =
                Number(
                    storage.getItem(key)
                );

            return Number.isSafeInteger(value)
                && value > 0
                ? value
                : 0;
        } catch (error) {
            return 0;
        }
    }

    function writeInteger(
        storage,
        key,
        value
    ) {
        try {
            storage.setItem(
                key,
                String(value)
            );
        } catch (error) {
            // Storage may be unavailable.
        }
    }

    function dispatchNotificationEvent(
        notification
    ) {
        window.dispatchEvent(
            new CustomEvent(
                'tabangnow:notification-received',
                {
                    detail: notification,
                }
            )
        );
    }

    async function checkForNotifications() {
        if (requestRunning) {
            return;
        }

        requestRunning = true;

        try {
            const response = await fetch(
                pulseUrl,
                {
                    method: 'GET',

                    credentials:
                        'same-origin',

                    headers: {
                        Accept:
                            'application/json',

                        'X-Requested-With':
                            'XMLHttpRequest',
                    },

                    cache:
                        'no-store',
                }
            );

            if (!response.ok) {
                return;
            }

            const contentType =
                response.headers.get(
                    'content-type'
                ) ?? '';

            if (
                !contentType.includes(
                    'application/json'
                )
            ) {
                return;
            }

            const payload =
                await response.json();

            const latestId =
                Number(
                    payload?.data
                        ?.latest_notification_id
                    ?? 0
                );

            if (
                !Number.isSafeInteger(latestId)
                || latestId <= 0
            ) {
                return;
            }

            const sessionCursor =
                readInteger(
                    window.sessionStorage,
                    sessionCursorKey
                );

            /*
            |--------------------------------------------------------------------------
            | Initial baseline
            |--------------------------------------------------------------------------
            |
            | Existing notifications must not make noise simply because the user
            | opened or refreshed TabangNow.
            |
            */
            if (sessionCursor === 0) {
                writeInteger(
                    window.sessionStorage,
                    sessionCursorKey,
                    latestId
                );

                const existingGlobalCursor =
                    readInteger(
                        window.localStorage,
                        lastSoundedKey
                    );

                if (
                    latestId
                    > existingGlobalCursor
                ) {
                    writeInteger(
                        window.localStorage,
                        lastSoundedKey,
                        latestId
                    );
                }

                return;
            }

            if (latestId <= sessionCursor) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | New notification detected
            |--------------------------------------------------------------------------
            */
            writeInteger(
                window.sessionStorage,
                sessionCursorKey,
                latestId
            );

            const lastSoundedId =
                readInteger(
                    window.localStorage,
                    lastSoundedKey
                );

            const notification =
                payload?.data?.notification
                ?? null;

            /*
             * Dispatching an application-level event keeps notification receipt
             * separate from the sound itself. Other web UI components may later
             * listen to this event without changing the polling layer.
             */
            dispatchNotificationEvent(
                notification
            );

            /*
             * localStorage provides a best-effort guard against multiple open
             * TabangNow tabs playing the same notification repeatedly.
             */
            if (latestId > lastSoundedId) {
                writeInteger(
                    window.localStorage,
                    lastSoundedKey,
                    latestId
                );

                playNotificationSound();
            }
        } catch (error) {
            /*
             * A temporary network/server failure must never break the page.
             */
            console.debug(
                'Notification pulse temporarily unavailable.',
                error
            );
        } finally {
            requestRunning = false;
        }
    }

    function startPolling() {
        if (pollTimer !== null) {
            return;
        }

        checkForNotifications();

        pollTimer = window.setInterval(
            checkForNotifications,
            POLL_INTERVAL_MS
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unlock audio after legitimate interaction
    |--------------------------------------------------------------------------
    */
    [
        'pointerdown',
        'keydown',
        'touchstart',
    ].forEach((eventName) => {
        document.addEventListener(
            eventName,
            unlockAudio,
            {
                once: true,
                passive: true,
            }
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Check immediately when the user returns to the tab
    |--------------------------------------------------------------------------
    */
    document.addEventListener(
        'visibilitychange',
        () => {
            if (!document.hidden) {
                checkForNotifications();
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Start globally
    |--------------------------------------------------------------------------
    */
    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            startPolling,
            {
                once: true,
            }
        );
    } else {
        startPolling();
    }
})();