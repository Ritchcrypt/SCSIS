import './presence-heartbeat';

/*
|--------------------------------------------------------------------------
| Global Image Upload Preview
|--------------------------------------------------------------------------
| Applies to image file inputs across the system:
| - system logo
| - profile photo
| - incident evidence
| - resident complaint proof
| - future image attachment fields
|
| This script is defensive:
| - Does not submit anything automatically
| - Does not affect non-image files
| - Supports single and multiple image inputs
| - Clears preview safely when user cancels file selection
*/

document.addEventListener('DOMContentLoaded', () => {
    initializeImageUploadPreviews();
});

function initializeImageUploadPreviews() {
    const fileInputs = document.querySelectorAll('input[type="file"]');

    fileInputs.forEach((input) => {
        if (!shouldEnableImagePreview(input)) {
            return;
        }

        if (input.dataset.previewInitialized === 'true') {
            return;
        }

        input.dataset.previewInitialized = 'true';

        const previewContainer = createPreviewContainer(input);
        input.insertAdjacentElement('afterend', previewContainer);

        let activeObjectUrls = [];

        input.addEventListener('change', () => {
            activeObjectUrls.forEach((url) => URL.revokeObjectURL(url));
            activeObjectUrls = [];

            previewContainer.innerHTML = '';

            const files = Array.from(input.files || []);
            const imageFiles = files.filter((file) => file.type.startsWith('image/'));

            if (imageFiles.length === 0) {
                previewContainer.classList.add('hidden');
                return;
            }

            previewContainer.classList.remove('hidden');

            const heading = document.createElement('p');
            heading.className = 'mb-3 text-xs font-bold uppercase tracking-wide text-slate-500';
            heading.textContent = imageFiles.length > 1 ? 'Selected image previews' : 'Selected image preview';
            previewContainer.appendChild(heading);

            const grid = document.createElement('div');
            grid.className = imageFiles.length > 1
                ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4'
                : 'max-w-xs';

            imageFiles.forEach((file) => {
                const objectUrl = URL.createObjectURL(file);
                activeObjectUrls.push(objectUrl);

                const card = document.createElement('div');
                card.className = 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm';

                const image = document.createElement('img');
                image.src = objectUrl;
                image.alt = file.name;
                image.className = 'h-40 w-full object-cover';

                const fileName = document.createElement('p');
                fileName.className = 'truncate px-3 py-2 text-xs font-semibold text-slate-600';
                fileName.textContent = file.name;

                card.appendChild(image);
                card.appendChild(fileName);
                grid.appendChild(card);
            });

            previewContainer.appendChild(grid);
        });
    });
}

function shouldEnableImagePreview(input) {
    if (input.dataset.imagePreview === 'true') {
        return true;
    }

    const accept = (input.getAttribute('accept') || '').toLowerCase();

    return accept.includes('image/')
        || accept.includes('.jpg')
        || accept.includes('.jpeg')
        || accept.includes('.png')
        || accept.includes('.webp')
        || accept.includes('.gif');
}

function createPreviewContainer(input) {
    const container = document.createElement('div');

    container.className = 'mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 p-3';
    container.dataset.previewFor = input.id || input.name || 'image-upload';

    return container;
}

/*
|--------------------------------------------------------------------------
| Global Scroll Position Keeper
|--------------------------------------------------------------------------
| Keeps the user's screen position after same-page actions such as:
| - delete forms
| - save/update forms
| - search/filter forms
| - pagination links
|
| This is global. It applies to all roles/pages that use resources/js/app.js.
*/

(function () {
    const namespace = 'tabangnow.scroll';
    const pendingKey = `${namespace}.pending`;
    const exactKeyPrefix = `${namespace}.exact.`;
    const restoreLifetimeMs = 5 * 60 * 1000;

    let saveTimer = null;
    let hasRestored = false;

    function now() {
        return Date.now();
    }

    function currentPageKey() {
        return `${window.location.pathname}${window.location.search}`;
    }

    function exactKey() {
        return `${exactKeyPrefix}${currentPageKey()}`;
    }

    function canUseStorage() {
        try {
            const testKey = `${namespace}.test`;
            window.sessionStorage.setItem(testKey, '1');
            window.sessionStorage.removeItem(testKey);

            return true;
        } catch (error) {
            return false;
        }
    }

    if (!canUseStorage()) {
        return;
    }
    
    if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
}

    function getScrollPosition() {
        return {
            x: window.scrollX || window.pageXOffset || 0,
            y: window.scrollY || window.pageYOffset || 0,
            path: window.location.pathname,
            page: currentPageKey(),
            savedAt: now(),
        };
    }

    function saveExactPosition() {
        try {
            window.sessionStorage.setItem(exactKey(), JSON.stringify(getScrollPosition()));
        } catch (error) {
            // Ignore storage write failures.
        }
    }

    function savePendingPosition() {
        try {
            const position = getScrollPosition();

            position.expiresAt = now() + restoreLifetimeMs;

            window.sessionStorage.setItem(pendingKey, JSON.stringify(position));
            saveExactPosition();
        } catch (error) {
            // Ignore storage write failures.
        }
    }

    function readJson(key) {
        try {
            const raw = window.sessionStorage.getItem(key);

            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function removeKey(key) {
        try {
            window.sessionStorage.removeItem(key);
        } catch (error) {
            // Ignore storage remove failures.
        }
    }

    function isUsablePosition(position) {
        if (!position || typeof position !== 'object') {
            return false;
        }

        if (typeof position.y !== 'number' || Number.isNaN(position.y)) {
            return false;
        }

        if (position.expiresAt && now() > position.expiresAt) {
            return false;
        }

        return true;
    }

    function shouldRestorePending(position) {
        if (!isUsablePosition(position)) {
            return false;
        }

        /*
         * Restore only when returning to the same module/path.
         * This prevents sidebar navigation from opening another module halfway down.
         */
        return position.path === window.location.pathname;
    }

    function restoreTo(position) {
    if (!isUsablePosition(position) || hasRestored) {
        return;
    }

    hasRestored = true;

    const targetX = Math.max(0, position.x || 0);
    const targetY = Math.max(0, position.y || 0);
    const root = document.documentElement;
    const previousScrollBehavior = root.style.scrollBehavior;

    root.style.scrollBehavior = 'auto';

    const scrollNow = function () {
        window.scrollTo({
            left: targetX,
            top: targetY,
            behavior: 'auto',
        });
    };

    scrollNow();

    requestAnimationFrame(scrollNow);
    window.setTimeout(scrollNow, 40);
    window.setTimeout(scrollNow, 120);
    window.setTimeout(scrollNow, 250);

    window.setTimeout(function () {
        root.style.scrollBehavior = previousScrollBehavior;
    }, 300);
}

    function restorePosition() {
        if (window.location.hash) {
            return;
        }

        const pendingPosition = readJson(pendingKey);

        if (shouldRestorePending(pendingPosition)) {
            removeKey(pendingKey);
            restoreTo(pendingPosition);

            return;
        }

        removeKey(pendingKey);

        const exactPosition = readJson(exactKey());

        if (isUsablePosition(exactPosition)) {
            restoreTo(exactPosition);
        }
    }

    function isModifiedClick(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
    }

    function shouldHandleLink(link, event) {
        if (!link || isModifiedClick(event)) {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (link.hasAttribute('download')) {
            return false;
        }

        if (link.hasAttribute('data-no-scroll-restore')) {
            return false;
        }

        const href = link.getAttribute('href');

        if (!href || href === '#' || href.startsWith('#')) {
            return false;
        }

        let url;

        try {
            url = new URL(href, window.location.href);
        } catch (error) {
            return false;
        }

        if (url.origin !== window.location.origin) {
            return false;
        }

        if (['mailto:', 'tel:', 'javascript:'].includes(url.protocol)) {
            return false;
        }

        /*
         * Only same-path links are restored globally.
         * This covers pagination and filter links.
         */
        return url.pathname === window.location.pathname;
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a');

        if (shouldHandleLink(link, event)) {
            savePendingPosition();
        }
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form || form.hasAttribute('data-no-scroll-restore')) {
            return;
        }

        savePendingPosition();
    }, true);

    window.addEventListener('scroll', function () {
        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }

        saveTimer = window.setTimeout(saveExactPosition, 150);
    }, { passive: true });

    window.addEventListener('beforeunload', function () {
        saveExactPosition();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restorePosition);
    } else {
        restorePosition();
    }
})();