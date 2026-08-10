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



    /*
    |--------------------------------------------------------------------------
    | Public Authentication Page Guard
    |--------------------------------------------------------------------------
    | Login, registration, password reset, and verification pages must always
    | begin at the top. Dashboard/module scroll restoration remains unchanged.
    */

    if (document.body?.classList.contains('tn-auth-body')) {
        const currentPage = `${window.location.pathname}${window.location.search}`;

        try {
            window.sessionStorage.removeItem(`${namespace}.pending`);
            window.sessionStorage.removeItem(`${namespace}.exact.${currentPage}`);
        } catch (error) {
            // Authentication must remain functional when storage is unavailable.
        }

        if ('scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'auto';
        }

        window.scrollTo({
            left: 0,
            top: 0,
            behavior: 'auto',
        });

        return;
    }
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

    /*
|--------------------------------------------------------------------------
| Global Native Tooltip Cleanup
|--------------------------------------------------------------------------
| Removes browser-native black tooltips created by:
| - HTML title="" attributes
| - SVG <title> elements
|
| Accessible labels are preserved before the tooltip source is removed.
| This applies globally, including Livewire-rendered content.
*/

(function () {
    function removeNativeTooltips(root = document) {
        const titledElements = [];

        if (
            root instanceof Element
            && root.matches('[title]')
        ) {
            titledElements.push(root);
        }

        if (typeof root.querySelectorAll === 'function') {
            titledElements.push(
                ...root.querySelectorAll('[title]')
            );
        }

        titledElements.forEach((element) => {
            const tooltipText = (
                element.getAttribute('title') || ''
            ).trim();

            /*
             * Preserve the text for screen readers when the element does not
             * already have another accessible name.
             */
            if (
                tooltipText
                && !element.hasAttribute('aria-label')
                && !element.hasAttribute('aria-labelledby')
            ) {
                element.setAttribute('aria-label', tooltipText);
            }

            element.removeAttribute('title');
        });

        const svgTitles = [];

        if (
            root instanceof Element
            && root.tagName.toLowerCase() === 'title'
            && root.parentElement?.tagName.toLowerCase() === 'svg'
        ) {
            svgTitles.push(root);
        }

        if (typeof root.querySelectorAll === 'function') {
            svgTitles.push(
                ...root.querySelectorAll('svg > title')
            );
        }

        svgTitles.forEach((titleElement) => {
            const svg = titleElement.parentElement;

            if (!svg) {
                return;
            }

            const tooltipText = (
                titleElement.textContent || ''
            ).trim();

            /*
             * Move the SVG title into aria-label before removing it.
             */
            if (
                tooltipText
                && !svg.hasAttribute('aria-label')
            ) {
                svg.setAttribute('aria-label', tooltipText);
            }

            const description = svg.querySelector(':scope > desc[id]');

            if (
                description
                && !svg.hasAttribute('aria-describedby')
            ) {
                svg.setAttribute(
                    'aria-describedby',
                    description.id
                );
            }

            /*
             * Prevent aria-labelledby from pointing to the removed SVG title.
             */
            const titleId = titleElement.id;

            if (titleId && svg.hasAttribute('aria-labelledby')) {
                const remainingIds = (
                    svg.getAttribute('aria-labelledby') || ''
                )
                    .split(/\s+/)
                    .filter(Boolean)
                    .filter((id) => id !== titleId);

                if (remainingIds.length > 0) {
                    svg.setAttribute(
                        'aria-labelledby',
                        remainingIds.join(' ')
                    );
                } else {
                    svg.removeAttribute('aria-labelledby');
                }
            }

            titleElement.remove();
        });
    }

    function startNativeTooltipCleanup() {
        removeNativeTooltips(document);

        if (!document.body) {
            return;
        }

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    removeNativeTooltips(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            startNativeTooltipCleanup,
            { once: true }
        );
    } else {
        startNativeTooltipCleanup();
    }

    document.addEventListener('livewire:navigated', () => {
        removeNativeTooltips(document);
    });
})();

/*
|--------------------------------------------------------------------------
| TabangNow Global Confirmation Modal
|--------------------------------------------------------------------------
| Replaces native browser confirm() dialogs used through inline onsubmit and
| onclick attributes. Confirmation safety is preserved without displaying
| browser messages such as "127.0.0.1:8000 says".
|
| It supports:
| - form submissions
| - activation and deactivation
| - deletion and removal
| - ordinary links and buttons
| - Livewire-rendered content
| - future elements using data-confirm
*/

(function () {
    const modalId = 'tn-global-confirmation';

    let pendingAction = null;
    let previouslyFocusedElement = null;

    /**
     * Extract a confirmation message from inline JavaScript such as:
     * return confirm('Delete this record?')
     */
    function extractConfirmMessage(inlineCode) {
        if (!inlineCode) {
            return '';
        }

        const match = inlineCode.match(
            /confirm\s*\(\s*(['"`])([\s\S]*?)\1\s*\)/i
        );

        if (!match) {
            return '';
        }

        return match[2]
            .replace(/\\'/g, "'")
            .replace(/\\"/g, '"')
            .replace(/\\`/g, '`')
            .replace(/\\\\/g, '\\')
            .trim();
    }

    function inferConfirmationTitle(message) {
        if (/activate/i.test(message)) {
            return 'Activate account';
        }

        if (/deactivate|disable/i.test(message)) {
            return 'Deactivate account';
        }

        if (/delete|remove|trash/i.test(message)) {
            return 'Delete record';
        }

        if (/reject|decline/i.test(message)) {
            return 'Confirm rejection';
        }

        if (/approve|accept/i.test(message)) {
            return 'Confirm approval';
        }

        return 'Confirm action';
    }

    function inferConfirmationButton(message) {
        if (/activate/i.test(message)) {
            return 'Activate';
        }

        if (/deactivate|disable/i.test(message)) {
            return 'Deactivate';
        }

        if (/delete|remove|trash/i.test(message)) {
            return 'Delete';
        }

        if (/reject/i.test(message)) {
            return 'Reject';
        }

        if (/decline/i.test(message)) {
            return 'Decline';
        }

        if (/approve/i.test(message)) {
            return 'Approve';
        }

        if (/accept/i.test(message)) {
            return 'Accept';
        }

        return 'Continue';
    }

    function isDangerousAction(message) {
        return /delete|remove|trash|deactivate|disable|reject|decline/i.test(
            message
        );
    }

    function createConfirmationModal() {
        const existingModal = document.getElementById(modalId);

        if (existingModal) {
            return existingModal;
        }

        const modal = document.createElement('div');

        modal.id = modalId;
        modal.className = 'tn-confirm-overlay';
        modal.hidden = true;

        modal.innerHTML = `
            <section
                class="tn-confirm-dialog"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="tn-confirm-title"
                aria-describedby="tn-confirm-message"
            >
                <div class="tn-confirm-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="M12 3.5 21 20H3L12 3.5Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M12 9v5"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                        <circle
                            cx="12"
                            cy="17"
                            r="1"
                            fill="currentColor"
                        />
                    </svg>
                </div>

                <div class="tn-confirm-content">
                    <h2 id="tn-confirm-title">
                        Confirm action
                    </h2>

                    <p id="tn-confirm-message">
                        Continue with this action?
                    </p>
                </div>

                <div class="tn-confirm-actions">
                    <button
                        type="button"
                        class="tn-confirm-button tn-confirm-button-cancel"
                        data-tn-confirm-cancel
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="tn-confirm-button tn-confirm-button-approve"
                        data-tn-confirm-approve
                    >
                        Continue
                    </button>
                </div>
            </section>
        `;

        document.body.appendChild(modal);

        modal
            .querySelector('[data-tn-confirm-cancel]')
            .addEventListener('click', () => {
                closeConfirmation(false);
            });

        modal
            .querySelector('[data-tn-confirm-approve]')
            .addEventListener('click', () => {
                closeConfirmation(true);
            });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeConfirmation(false);
            }
        });

        return modal;
    }

    function openConfirmation({
        message,
        title = '',
        confirmText = '',
        dangerous = null,
        onConfirm,
    }) {
        const modal = createConfirmationModal();

        const finalTitle = title || inferConfirmationTitle(message);
        const finalConfirmText =
            confirmText || inferConfirmationButton(message);

        const finalDangerousState =
            dangerous === null
                ? isDangerousAction(message)
                : dangerous;

        const titleElement = modal.querySelector(
            '#tn-confirm-title'
        );

        const messageElement = modal.querySelector(
            '#tn-confirm-message'
        );

        const approveButton = modal.querySelector(
            '[data-tn-confirm-approve]'
        );

        titleElement.textContent = finalTitle;
        messageElement.textContent =
            message || 'Continue with this action?';

        approveButton.textContent = finalConfirmText;

        modal.classList.toggle(
            'is-dangerous',
            finalDangerousState
        );

        pendingAction =
            typeof onConfirm === 'function'
                ? onConfirm
                : null;

        previouslyFocusedElement =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;

        modal.hidden = false;

        document.body.classList.add('tn-confirm-open');

        const cancelButton = modal.querySelector(
            '[data-tn-confirm-cancel]'
        );

        window.requestAnimationFrame(() => {
            cancelButton.focus({
                preventScroll: true,
            });
        });
    }

    function closeConfirmation(confirmed) {
        const modal = document.getElementById(modalId);

        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;

        document.body.classList.remove('tn-confirm-open');

        const action = pendingAction;

        pendingAction = null;

        if (
            previouslyFocusedElement
            && document.contains(previouslyFocusedElement)
        ) {
            previouslyFocusedElement.focus({
                preventScroll: true,
            });
        }

        previouslyFocusedElement = null;

        if (confirmed && typeof action === 'function') {
            window.setTimeout(action, 0);
        }
    }

    /**
     * Converts existing inline confirm() attributes into global modal data.
     */
    function initializeConfirmationElement(element) {
        if (!(element instanceof Element)) {
            return;
        }

        if (element.dataset.tnConfirmInitialized === 'true') {
            return;
        }

        let message = (
            element.getAttribute('data-confirm') || ''
        ).trim();

        if (element instanceof HTMLFormElement) {
            const inlineSubmit =
                element.getAttribute('onsubmit') || '';

            message =
                message ||
                extractConfirmMessage(inlineSubmit);

            if (
                message
                && /confirm\s*\(/i.test(inlineSubmit)
            ) {
                element.removeAttribute('onsubmit');
            }
        } else {
            const inlineClick =
                element.getAttribute('onclick') || '';

            message =
                message ||
                extractConfirmMessage(inlineClick);

            if (
                message
                && /confirm\s*\(/i.test(inlineClick)
            ) {
                element.removeAttribute('onclick');
            }
        }

        if (!message) {
            return;
        }

        element.dataset.tnConfirmMessage = message;
        element.dataset.tnConfirmInitialized = 'true';
    }

    function initializeConfirmations(root = document) {
        const candidates = [];

        if (
            root instanceof Element
            && (
                root.hasAttribute('data-confirm')
                || root.hasAttribute('onsubmit')
                || root.hasAttribute('onclick')
            )
        ) {
            candidates.push(root);
        }

        if (typeof root.querySelectorAll === 'function') {
            candidates.push(
                ...root.querySelectorAll(
                    '[data-confirm], [onsubmit], [onclick]'
                )
            );
        }

        candidates.forEach((element) => {
            const inlineCode = element instanceof HTMLFormElement
                ? element.getAttribute('onsubmit')
                : element.getAttribute('onclick');

            if (
                element.hasAttribute('data-confirm')
                || /confirm\s*\(/i.test(inlineCode || '')
            ) {
                initializeConfirmationElement(element);
            }
        });
    }

    /**
     * Intercept confirmed form submissions.
     */
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const submitter =
                event.submitter instanceof HTMLElement
                    ? event.submitter
                    : null;

            const message =
                submitter?.dataset.tnConfirmMessage
                || form.dataset.tnConfirmMessage
                || '';

            if (!message) {
                return;
            }

            if (form.dataset.tnConfirmApproved === 'true') {
                delete form.dataset.tnConfirmApproved;

                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            openConfirmation({
                message,
                title:
                    submitter?.dataset.confirmTitle
                    || form.dataset.confirmTitle
                    || '',
                confirmText:
                    submitter?.dataset.confirmButton
                    || form.dataset.confirmButton
                    || '',
                dangerous:
                    (
                        submitter?.dataset.confirmTone
                        || form.dataset.confirmTone
                    ) === 'danger'
                        ? true
                        : null,
                onConfirm: () => {
                    form.dataset.tnConfirmApproved = 'true';

                    if (typeof form.requestSubmit === 'function') {
                        if (
                            submitter instanceof HTMLButtonElement
                            || submitter instanceof HTMLInputElement
                        ) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }

                        return;
                    }

                    form.submit();
                },
            });
        },
        true
    );

    /**
     * Intercept confirmed links and non-submit buttons.
     */
    document.addEventListener(
        'click',
        (event) => {
            const element = event.target.closest(
                '[data-tn-confirm-message], [data-confirm]'
            );

            if (!element) {
                return;
            }

            const isSubmitControl =
                (
                    element instanceof HTMLButtonElement
                    || element instanceof HTMLInputElement
                )
                && element.form
                && (
                    element.type === 'submit'
                    || element.getAttribute('type') === null
                );

            if (isSubmitControl) {
                return;
            }

            const message =
                element.dataset.tnConfirmMessage
                || element.getAttribute('data-confirm')
                || '';

            if (!message) {
                return;
            }

            if (element.dataset.tnConfirmApproved === 'true') {
                delete element.dataset.tnConfirmApproved;

                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            openConfirmation({
                message,
                title: element.dataset.confirmTitle || '',
                confirmText:
                    element.dataset.confirmButton || '',
                dangerous:
                    element.dataset.confirmTone === 'danger'
                        ? true
                        : null,
                onConfirm: () => {
                    element.dataset.tnConfirmApproved = 'true';
                    element.click();
                },
            });
        },
        true
    );

    document.addEventListener('keydown', (event) => {
        const modal = document.getElementById(modalId);

        if (!modal || modal.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeConfirmation(false);
        }
    });

    function startGlobalConfirmations() {
        initializeConfirmations(document);

        if (!document.body) {
            return;
        }

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    initializeConfirmations(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            startGlobalConfirmations,
            { once: true }
        );
    } else {
        startGlobalConfirmations();
    }

    document.addEventListener('livewire:navigated', () => {
        initializeConfirmations(document);
    });

    /**
     * Available for future JavaScript-controlled actions:
     *
     * window.TabangNowConfirm.open({
     *     message: 'Continue?',
     *     onConfirm: () => {}
     * });
     */
    window.TabangNowConfirm = {
        open: openConfirmation,
        close: closeConfirmation,
    };
})();
})();

import './notification-sound';