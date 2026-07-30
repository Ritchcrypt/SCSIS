@props([
    'title',
    'description',
])

<div class="tn-auth-header">
    <span class="tn-auth-header-eyebrow">
        Secure access
    </span>

    <h1>
        {{ $title }}
    </h1>

    <p>
        {{ $description }}
    </p>
</div>