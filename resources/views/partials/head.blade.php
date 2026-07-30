<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{ $title ?? config('app.name', 'TabangNow') }}</title>

<link
    rel="icon"
    type="image/png"
    sizes="32x32"
    href="{{ asset('tabangnow-tab-icon-v7.png') }}?v=10"
>

<link
    rel="shortcut icon"
    type="image/png"
    href="{{ asset('tabangnow-tab-icon-v7.png') }}?v=10"
>

<link rel="preconnect" href="https://fonts.bunny.net">

<link
    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900"
    rel="stylesheet"
/>

@vite([
    'resources/css/app.css',
    'resources/js/app.js',
])

@fluxAppearance