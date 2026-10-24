@php
    $dashboardWelcomeName = trim((string) (auth()->user()?->name ?? 'User'));
    $dashboardWelcomeName = $dashboardWelcomeName !== '' ? $dashboardWelcomeName : 'User';
@endphp

<style>
    .tn-c21-dashboard-welcome {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        display: flex;
        align-items: center;
        min-height: 236px;
        margin-bottom: 28px;
        background-color: #f8fafc;
        border-radius: 12px;
    }

    .tn-c21-dashboard-welcome::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -2;
        background-image: url("{{ asset('images/dashboard/dao-welcome-scene-20261022_173013_735.jpg') }}");
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

<section class="tn-c21-dashboard-welcome" aria-label="Dashboard welcome">
    <div class="tn-c21-dashboard-welcome__text">
        <p class="tn-c21-dashboard-welcome__intro">Welcome back,</p>
        <h1 class="tn-c21-dashboard-welcome__name">{{ $dashboardWelcomeName }}</h1>

    </div>
</section>
