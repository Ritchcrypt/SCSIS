(() => {
    if (window.__tabangNowNotificationSoundInstalled) {
        return;
    }

    window.__tabangNowNotificationSoundInstalled = true;

    const pulseUrl = document
        .querySelector('meta[name="notification-pulse-url"]')
        ?.getAttribute('content');

    const userId = document
        .querySelector('meta[name="notification-user-id"]')
        ?.getAttribute('content');

    if (!pulseUrl || !userId) {
        return;
    }

    const POLL_INTERVAL_MS = 15000;
    const sessionCursorKey = `tabangnow.notification.cursor.${userId}`;
    const lastSoundedKey = `tabangnow.notification.last-sounded.${userId}`;

    let audioContext = null;
    let audioReady = false;
    let requestRunning = false;
    let pollTimer = null;

    function unlockAudio() {
        if (audioReady) {
            return;
        }

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) {
            return;
        }

        try {
            if (!audioContext) {
                audioContext = new AudioContextClass();
            }

            const resumePromise = audioContext.state === 'suspended'
                ? audioContext.resume()
                : Promise.resolve();

            Promise.resolve(resumePromise)
                .then(() => {
                    audioReady = audioContext?.state === 'running';
                })
                .catch(() => {
                    audioReady = false;
                });
        } catch (error) {
            console.warn('Notification audio could not be enabled.', error);
        }
    }

    function playTone({
        frequency,
        start,
        duration,
        volume,
        type = 'sine',
    }) {
        if (!audioContext) {
            return;
        }

        const gain = audioContext.createGain();
        const oscillator = audioContext.createOscillator();

        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume, start + 0.015);
        gain.gain.exponentialRampToValueAtTime(
            0.0001,
            start + Math.max(0.03, duration)
        );

        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, start);
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start(start);
        oscillator.stop(start + duration);
    }

    function canPlayAudio() {
        return Boolean(
            audioReady
            && audioContext
            && audioContext.state === 'running'
        );
    }

    function playNotificationSound() {
        if (!canPlayAudio()) {
            return;
        }

        try {
            const start = audioContext.currentTime;

            playTone({
                frequency: 880,
                start,
                duration: 0.22,
                volume: 0.16,
            });

            playTone({
                frequency: 1174.66,
                start: start + 0.18,
                duration: 0.30,
                volume: 0.16,
            });
        } catch (error) {
            console.warn('Notification sound could not be played.', error);
        }
    }

    function playEmergencyAlarm() {
        if (!canPlayAudio()) {
            return;
        }

        try {
            const start = audioContext.currentTime;

            /*
             * Three urgent alternating bursts. The pattern is intentionally
             * distinct from ordinary TabangNow notification chimes.
             */
            for (let cycle = 0; cycle < 3; cycle += 1) {
                const base = start + (cycle * 0.78);

                playTone({
                    frequency: 960,
                    start: base,
                    duration: 0.28,
                    volume: 0.30,
                    type: 'square',
                });

                playTone({
                    frequency: 640,
                    start: base + 0.30,
                    duration: 0.28,
                    volume: 0.30,
                    type: 'square',
                });
            }

            if (typeof navigator.vibrate === 'function') {
                navigator.vibrate([
                    250, 120,
                    250, 120,
                    250, 120,
                    500,
                ]);
            }
        } catch (error) {
            console.warn('Emergency notification alarm could not be played.', error);
        }
    }

    function isEmergencyNotification(notification) {
        const type = String(notification?.type ?? '')
            .trim()
            .toLowerCase();

        return [
            'mobile_emergency',
            'emergency',
            'calamity',
        ].includes(type);
    }

    function readInteger(storage, key) {
        try {
            const value = Number(storage.getItem(key));

            return Number.isSafeInteger(value) && value > 0
                ? value
                : 0;
        } catch (error) {
            return 0;
        }
    }

    function writeInteger(storage, key, value) {
        try {
            storage.setItem(key, String(value));
        } catch (error) {
            // Storage may be unavailable.
        }
    }

    function dispatchNotificationEvent(notification) {
        window.dispatchEvent(
            new CustomEvent(
                'tabangnow:notification-received',
                { detail: notification }
            )
        );
    }

    async function checkForNotifications() {
        if (requestRunning) {
            return;
        }

        requestRunning = true;

        try {
            const response = await fetch(pulseUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const contentType = response.headers.get('content-type') ?? '';

            if (!contentType.includes('application/json')) {
                return;
            }

            const payload = await response.json();
            const latestId = Number(
                payload?.data?.latest_notification_id ?? 0
            );

            if (!Number.isSafeInteger(latestId) || latestId <= 0) {
                return;
            }

            const sessionCursor = readInteger(
                window.sessionStorage,
                sessionCursorKey
            );

            /*
             * Establish a baseline silently so an old unread notification does
             * not trigger audio merely because the user refreshed the page.
             */
            if (sessionCursor === 0) {
                writeInteger(
                    window.sessionStorage,
                    sessionCursorKey,
                    latestId
                );

                const existingGlobalCursor = readInteger(
                    window.localStorage,
                    lastSoundedKey
                );

                if (latestId > existingGlobalCursor) {
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

            writeInteger(
                window.sessionStorage,
                sessionCursorKey,
                latestId
            );

            const lastSoundedId = readInteger(
                window.localStorage,
                lastSoundedKey
            );

            const notification = payload?.data?.notification ?? null;

            dispatchNotificationEvent(notification);

            if (latestId > lastSoundedId) {
                writeInteger(
                    window.localStorage,
                    lastSoundedKey,
                    latestId
                );

                if (isEmergencyNotification(notification)) {
                    playEmergencyAlarm();
                } else {
                    playNotificationSound();
                }
            }
        } catch (error) {
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

    document.addEventListener(
        'visibilitychange',
        () => {
            if (!document.hidden) {
                checkForNotifications();
            }
        }
    );

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            startPolling,
            { once: true }
        );
    } else {
        startPolling();
    }
})();
