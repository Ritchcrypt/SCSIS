const heartbeatUrl = '/presence/heartbeat';
const heartbeatMilliseconds = 45000;

function getCsrfToken() {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';
}

async function sendPresenceHeartbeat() {
    try {
        const response = await fetch(heartbeatUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({}),
        });

        if (!response.ok) {
            console.warn(
                'Presence heartbeat failed:',
                response.status
            );
        }
    } catch (error) {
        console.warn(
            'Unable to update online presence.',
            error
        );
    }
}

document.addEventListener('DOMContentLoaded', function () {
    sendPresenceHeartbeat();

    window.setInterval(
        sendPresenceHeartbeat,
        heartbeatMilliseconds
    );
});

document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
        sendPresenceHeartbeat();
    }
});

window.addEventListener('focus', sendPresenceHeartbeat);