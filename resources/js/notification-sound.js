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
    const emergencySessionCursorKey = `tabangnow.notification.emergency-cursor.${userId}`;
    const emergencyLastSoundedKey = `tabangnow.notification.emergency-last-sounded.${userId}`;

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
            const emergencyId = Number(
                payload?.data?.latest_emergency_notification_id ?? 0
            );

            const validLatestId = Number.isSafeInteger(latestId) && latestId > 0;
            const validEmergencyId = Number.isSafeInteger(emergencyId) && emergencyId > 0;

            if (!validLatestId && !validEmergencyId) {
                return;
            }

            const sessionCursor = readInteger(
                window.sessionStorage,
                sessionCursorKey
            );
            const emergencySessionCursor = readInteger(
                window.sessionStorage,
                emergencySessionCursorKey
            );

            /*
             * Establish independent silent baselines. The emergency cursor is
             * deliberately separate so a newer ordinary notification can never
             * hide an SOS that arrived during the same polling interval.
             */
            if (sessionCursor === 0 && validLatestId) {
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
            }

            if (emergencySessionCursor === 0 && validEmergencyId) {
                writeInteger(
                    window.sessionStorage,
                    emergencySessionCursorKey,
                    emergencyId
                );

                const existingEmergencyGlobalCursor = readInteger(
                    window.localStorage,
                    emergencyLastSoundedKey
                );

                if (emergencyId > existingEmergencyGlobalCursor) {
                    writeInteger(
                        window.localStorage,
                        emergencyLastSoundedKey,
                        emergencyId
                    );
                }
            }

            let newEmergencyObserved = false;
            let emergencyNotificationId = 0;

            if (
                validEmergencyId
                && emergencySessionCursor > 0
                && emergencyId > emergencySessionCursor
            ) {
                newEmergencyObserved = true;

                writeInteger(
                    window.sessionStorage,
                    emergencySessionCursorKey,
                    emergencyId
                );

                const emergencyNotification = payload?.data?.emergency_notification ?? null;
                emergencyNotificationId = Number(emergencyNotification?.id ?? emergencyId);

                dispatchNotificationEvent(emergencyNotification);

                const lastEmergencySoundedId = readInteger(
                    window.localStorage,
                    emergencyLastSoundedKey
                );

                if (emergencyId > lastEmergencySoundedId) {
                    writeInteger(
                        window.localStorage,
                        emergencyLastSoundedKey,
                        emergencyId
                    );

                    playEmergencyAlarm();
                }
            }

            if (
                !validLatestId
                || sessionCursor === 0
                || latestId <= sessionCursor
            ) {
                return;
            }

            writeInteger(
                window.sessionStorage,
                sessionCursorKey,
                latestId
            );

            const notification = payload?.data?.notification ?? null;
            const notificationId = Number(notification?.id ?? latestId);

            if (
                !newEmergencyObserved
                || notificationId !== emergencyNotificationId
            ) {
                dispatchNotificationEvent(notification);
            }

            const lastSoundedId = readInteger(
                window.localStorage,
                lastSoundedKey
            );

            if (latestId <= lastSoundedId) {
                return;
            }

            writeInteger(
                window.localStorage,
                lastSoundedKey,
                latestId
            );

            /*
             * If a fresh mobile SOS was observed during this same poll, its
             * emergency alarm takes priority over any newer ordinary chime.
             */
            if (newEmergencyObserved) {
                return;
            }

            if (isEmergencyNotification(notification)) {
                playEmergencyAlarm();
            } else {
                playNotificationSound();
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
