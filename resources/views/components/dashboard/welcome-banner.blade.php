@php
    $dashboardWelcomeName = trim((string) (auth()->user()?->name ?? 'User'));
    $dashboardWelcomeName = $dashboardWelcomeName !== '' ? $dashboardWelcomeName : 'User';
    $dashboardWelcomeVersion = \App\Http\Controllers\DashboardWelcomeImageController::version();
    $dashboardWelcomeImageUrl = route('dashboard-welcome.image', ['v' => $dashboardWelcomeVersion]);
    $dashboardWelcomeIsAdmin = auth()->user()?->role === 'admin';
@endphp

<style>
    /* Keep the photo clipped but let its admin menu extend beyond the banner. */
    .tn-c21-dashboard-welcome-shell {
        position: relative;
        margin-bottom: 28px;
    }

    .tn-c21-dashboard-welcome {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        display: flex;
        align-items: center;
        min-height: 236px;
        background-color: #f8fafc;
        border-radius: 12px;
    }

    .tn-c21-dashboard-welcome::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -2;
        background-image: var(--tn-welcome-photo);
        background-position: center 44%;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .tn-c21-dashboard-welcome::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(90deg,
            #f8fafc 0%,
            rgba(248, 250, 252, 0.97) 29%,
            rgba(248, 250, 252, 0.74) 46%,
            rgba(248, 250, 252, 0.15) 76%,
            rgba(248, 250, 252, 0) 100%);
    }

    .tn-c21-dashboard-welcome__text {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 680px;
        padding: 30px 38px;
        color: #10294f;
    }

    /* Admin-only one-click photo picker. No flyout or image preview. */
    .tn-c21-dashboard-welcome__photo-tools {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 30;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        color: #f8fafc;
    }
    .tn-c21-dashboard-welcome__photo-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 1px solid rgba(255, 255, 255, 0.70);
        background: rgba(15, 23, 42, 0.90);
        color: #fff;
        border-radius: 9px;
        padding: 9px 12px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.18);
    }
    .tn-c21-dashboard-welcome__photo-button:hover:not(:disabled) { background: #1d4ed8; }
    .tn-c21-dashboard-welcome__photo-button:focus-visible {
        outline: 3px solid #93c5fd;
        outline-offset: 3px;
    }
    .tn-c21-dashboard-welcome__photo-button:disabled { opacity: .8; cursor: progress; }
    .tn-c21-dashboard-welcome__file-input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .tn-c21-dashboard-welcome__upload-status {
        display: block;
        max-width: min(280px, calc(100vw - 40px));
        background: rgba(15, 23, 42, .93);
        border: 1px solid rgba(148, 163, 184, .7);
        border-radius: 8px;
        padding: 7px 10px;
        color: #fff;
        font-size: 12px;
        overflow-wrap: anywhere;
        text-align: right;
    }
    .tn-c21-dashboard-welcome__upload-status[hidden] { display: none; }
    .tn-c21-dashboard-welcome__feedback {
        position: absolute;
        left: 38px;
        bottom: 12px;
        z-index: 3;
        font-size: 12px;
        color: #fff;
        background: rgba(15, 23, 42, .85);
        padding: 5px 10px;
        border-radius: 6px;
    }

    .tn-c21-dashboard-welcome__intro {
        margin: 0 0 4px;
        font-size: clamp(17px, 1.75vw, 22px);
        font-weight: 500;
        line-height: 1.35;
    }

    .tn-c21-dashboard-welcome__name {
        margin: 0;
        overflow-wrap: anywhere;
        font-size: clamp(30px, 3.2vw, 45px);
        font-weight: 800;
        line-height: 1.12;
        letter-spacing: -0.035em;
    }

    html[data-theme='dark'] .tn-c21-dashboard-welcome {
        background-color: #0f172a;
    }

    html[data-theme='dark'] .tn-c21-dashboard-welcome::after {
        background: linear-gradient(90deg,
            #0f172a 0%,
            rgba(15, 23, 42, 0.96) 30%,
            rgba(15, 23, 42, 0.75) 49%,
            rgba(15, 23, 42, 0.10) 80%,
            rgba(15, 23, 42, 0) 100%);
    }

    html[data-theme='dark'] .tn-c21-dashboard-welcome__text {
        color: #f8fafc;
    }

    @media (prefers-color-scheme: dark) {
        html[data-theme='system'] .tn-c21-dashboard-welcome {
            background-color: #0f172a;
        }
        html[data-theme='system'] .tn-c21-dashboard-welcome::after {
            background: linear-gradient(90deg,
                #0f172a 0%,
                rgba(15, 23, 42, 0.96) 30%,
                rgba(15, 23, 42, 0.75) 49%,
                rgba(15, 23, 42, 0.10) 80%,
                rgba(15, 23, 42, 0) 100%);
        }
        html[data-theme='system'] .tn-c21-dashboard-welcome__text {
            color: #f8fafc;
        }
    }

    @media (max-width: 640px) {
        .tn-c21-dashboard-welcome__photo-tools { right: 10px; top: 10px; }
        .tn-c21-dashboard-welcome {
            min-height: 218px;
        }
        .tn-c21-dashboard-welcome::after {
            background: linear-gradient(90deg,
                rgba(248, 250, 252, 0.97) 0%,
                rgba(248, 250, 252, 0.90) 58%,
                rgba(248, 250, 252, 0.33) 100%);
        }
        html[data-theme='dark'] .tn-c21-dashboard-welcome::after {
            background: linear-gradient(90deg,
                rgba(15, 23, 42, 0.97) 0%,
                rgba(15, 23, 42, 0.91) 58%,
                rgba(15, 23, 42, 0.35) 100%);
        }
        .tn-c21-dashboard-welcome__text {
            padding: 24px;
        }
    }

    @media (max-width: 640px) and (prefers-color-scheme: dark) {
        html[data-theme='system'] .tn-c21-dashboard-welcome::after {
            background: linear-gradient(90deg,
                rgba(15, 23, 42, 0.97) 0%,
                rgba(15, 23, 42, 0.91) 58%,
                rgba(15, 23, 42, 0.35) 100%);
        }
    }
</style>

<div class="tn-c21-dashboard-welcome-shell">
<section class="tn-c21-dashboard-welcome" aria-label="Dashboard welcome"
    data-welcome-version="{{ $dashboardWelcomeVersion }}"
    data-status-url="{{ route('dashboard-welcome.status') }}"
    style="--tn-welcome-photo: url('{{ $dashboardWelcomeImageUrl }}');">
    <div class="tn-c21-dashboard-welcome__text">
        <p class="tn-c21-dashboard-welcome__intro">Welcome back,</p>
        <h1 class="tn-c21-dashboard-welcome__name">{{ $dashboardWelcomeName }}</h1>
    </div>

    </section>

    @if ($dashboardWelcomeIsAdmin)
        <form class="tn-c21-dashboard-welcome__photo-tools"
              method="POST"
              action="{{ route('admin.dashboard-welcome-image.update') }}"
              enctype="multipart/form-data">
            @csrf
            <input id="dashboard-welcome-upload"
                   class="tn-c21-dashboard-welcome__file-input"
                   type="file" name="welcome_image"
                   accept="image/jpeg,image/png,image/webp"
                   aria-label="Choose a shared dashboard image"
                   required>
            <button type="button" class="tn-c21-dashboard-welcome__photo-button"
                    data-welcome-photo-trigger
                    aria-controls="dashboard-welcome-upload">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 4h-4l-2 3H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-4l-2-3Z"/><circle cx="12" cy="13" r="3"/></svg>
                <span data-welcome-photo-label>Change photo</span>
            </button>
            <span class="tn-c21-dashboard-welcome__upload-status"
                  data-welcome-upload-status role="status" aria-live="polite" hidden></span>
        </form>
        @if (session('welcome_image_success'))
            <p role="status" class="tn-c21-dashboard-welcome__feedback">{{ session('welcome_image_success') }}</p>
        @endif
        @error('welcome_image')
            <p role="alert" class="tn-c21-dashboard-welcome__feedback">{{ $message }}</p>
        @enderror
    @endif
    @include('components.dashboard.dao-news-carousel')
</div>
<script>
    (() => {
        const uploadInput = document.getElementById('dashboard-welcome-upload');
        const uploadForm = uploadInput?.form;
        const uploadButton = uploadForm?.querySelector('[data-welcome-photo-trigger]');
        const uploadLabel = uploadForm?.querySelector('[data-welcome-photo-label]');
        const uploadStatus = uploadForm?.querySelector('[data-welcome-upload-status]');

        const showUploadStatus = (message) => {
            if (!uploadStatus) return;
            uploadStatus.textContent = message;
            uploadStatus.hidden = !message;
        };

        // This button opens the operating system's file picker directly.
        uploadButton?.addEventListener('click', () => uploadInput?.click());
        uploadInput?.addEventListener('change', (event) => {
            // Suppress only this field's global automatic image-preview handler.
            // The global script and all other file inputs remain unchanged.
            event.stopImmediatePropagation();
            const file = uploadInput.files?.[0];
            if (!file || !uploadForm || !uploadButton) return;

            if (file.size > 5 * 1024 * 1024) {
                showUploadStatus('Image too large. Choose one under 5 MB.');
                uploadInput.value = '';
                return;
            }
            if (file.type && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                showUploadStatus('Choose a JPG, PNG, or WebP image.');
                uploadInput.value = '';
                return;
            }

            uploadButton.disabled = true;
            uploadButton.setAttribute('aria-busy', 'true');
            if (uploadLabel) uploadLabel.textContent = 'Uploading photo...';
            showUploadStatus('Saving for all dashboard users...');
            // The existing Laravel route validates, stores, and shares the image.
            // No popup, preview, or second Save button is required.
            if (typeof uploadForm.requestSubmit === 'function') {
                uploadForm.requestSubmit();
            } else {
                uploadForm.submit();
            }
        }, true);

        const banner = document.querySelector('.tn-c21-dashboard-welcome[data-status-url]');
        if (!banner) return;
        const refresh = async () => {
            if (document.visibilityState === 'hidden') return;
            try {
                const response = await fetch(banner.dataset.statusUrl, {
                    credentials: 'same-origin', cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (data.version && data.url && data.version !== banner.dataset.welcomeVersion) {
                    banner.style.setProperty('--tn-welcome-photo', `url("${data.url}")`);
                    banner.dataset.welcomeVersion = data.version;
                }
            } catch (_) { /* Continue displaying the last known image. */ }
        };
        const timer = window.setInterval(refresh, 20000);
        window.addEventListener('pagehide', () => window.clearInterval(timer), { once: true });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refresh();
        });
    })();
</script>
