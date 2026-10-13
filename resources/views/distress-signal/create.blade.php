<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">
                Emergency Response
            </p>

            <h1 class="mt-2 text-2xl font-black text-slate-950">
                Send a Distress Signal
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-600">
                Send your identity, mobile number, emergency details, and current location to TabangNow responders.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-black">
                    The distress signal was not sent.
                </p>

                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            id="websiteDistressSignalForm"
            method="POST"
            action="{{ route('distress-signal.store') }}"
            class="grid gap-5"
        >
            @csrf

            @if ($registeredUser)
                <div class="grid gap-2">
                    <label
                        for="registered_reporter_name"
                        class="text-sm font-bold text-slate-800"
                    >
                        Reporter full name
                    </label>

                    <input
                        id="registered_reporter_name"
                        type="text"
                        value="{{ $registeredUser->name }}"
                        readonly
                        class="w-full rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm"
                    >

                    <p class="text-xs leading-5 text-blue-800">
                        Filled automatically from your TabangNow account. The server will use this registered name even if the page is modified.
                    </p>
                </div>
            @else
                <div class="grid gap-2">
                    <label
                        for="reporter_name"
                        class="text-sm font-bold text-slate-800"
                    >
                        Reporter full name
                    </label>

                    <input
                        id="reporter_name"
                        name="reporter_name"
                        type="text"
                        value="{{ old('reporter_name') }}"
                        required
                        maxlength="255"
                        autocomplete="name"
                        placeholder="Example: Juan Dela Cruz"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                    >

                    <p class="text-xs leading-5 text-slate-500">
                        Unregistered reporters must provide a complete name with at least a first name and last name.
                    </p>
                </div>
            @endif

            <div class="grid gap-2">
                <label
                    for="contact_number"
                    class="text-sm font-bold text-slate-800"
                >
                    Mobile number
                </label>

                <input
                    id="contact_number"
                    name="contact_number"
                    type="tel"
                    value="{{ old('contact_number', $registeredUser?->contact_number) }}"
                    required
                    inputmode="tel"
                    autocomplete="tel"
                    maxlength="13"
                    placeholder="09123456789"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                >
            </div>

            <div class="grid gap-2">
                <label
                    for="emergency_details"
                    class="text-sm font-bold text-slate-800"
                >
                    Emergency details
                </label>

                <textarea
                    id="emergency_details"
                    name="emergency_details"
                    rows="5"
                    required
                    maxlength="1000"
                    placeholder="Describe what happened, who needs help, and any immediate danger."
                    class="w-full resize-y rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                >{{ old('emergency_details') }}</textarea>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-slate-900">
                            Current location
                        </p>

                        <p
                            id="distressLocationStatus"
                            class="mt-1 text-xs leading-5 text-slate-600"
                        >
                            Your current location must be captured before sending.
                        </p>
                    </div>

                    <button
                        id="captureDistressLocation"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-2.5 text-sm font-bold text-blue-800 shadow-sm hover:bg-blue-50"
                    >
                        Get Current Location
                    </button>
                </div>

                <input
                    id="distressLatitude"
                    name="latitude"
                    type="hidden"
                    value="{{ old('latitude') }}"
                >

                <input
                    id="distressLongitude"
                    name="longitude"
                    type="hidden"
                    value="{{ old('longitude') }}"
                >

                <input
                    id="distressAccuracy"
                    name="accuracy_meters"
                    type="hidden"
                    value="{{ old('accuracy_meters') }}"
                >
            </div>

            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-800">
                Use this only for a real distress situation. If the website cannot send or capture your location, contact the appropriate emergency hotline directly.
            </div>

            <button
                id="sendDistressSignalButton"
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3.5 text-sm font-black text-white shadow-sm hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                Send Distress Signal
            </button>
        </form>

        <div class="flex flex-wrap items-center justify-center gap-4 text-sm">
            @auth
                <a
                    href="{{ route('dashboard') }}"
                    class="font-bold text-blue-700 hover:text-blue-900"
                >
                    Return to Dashboard
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="font-bold text-blue-700 hover:text-blue-900"
                >
                    Return to Login
                </a>
            @endauth
        </div>
    </div>

    <script>
        (() => {
            const form =
                document.getElementById(
                    'websiteDistressSignalForm'
                );

            const captureButton =
                document.getElementById(
                    'captureDistressLocation'
                );

            const status =
                document.getElementById(
                    'distressLocationStatus'
                );

            const latitude =
                document.getElementById(
                    'distressLatitude'
                );

            const longitude =
                document.getElementById(
                    'distressLongitude'
                );

            const accuracy =
                document.getElementById(
                    'distressAccuracy'
                );

            const sendButton =
                document.getElementById(
                    'sendDistressSignalButton'
                );

            function hasLocation() {
                return latitude.value.trim() !== ''
                    && longitude.value.trim() !== '';
            }

            function refreshLocationState() {
                if (!hasLocation()) {
                    return;
                }

                status.textContent =
                    `Location ready: ${Number(latitude.value).toFixed(6)}, ${Number(longitude.value).toFixed(6)}`;

                status.className =
                    'mt-1 text-xs font-bold leading-5 text-emerald-700';
            }

            async function captureLocation() {
                if (!navigator.geolocation) {
                    status.textContent =
                        'Location services are not supported by this browser.';

                    status.className =
                        'mt-1 text-xs font-bold leading-5 text-red-700';

                    return;
                }

                captureButton.disabled =
                    true;

                captureButton.textContent =
                    'Getting Location...';

                status.textContent =
                    'Requesting your current device location...';

                status.className =
                    'mt-1 text-xs leading-5 text-slate-600';

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        latitude.value =
                            String(
                                position.coords.latitude
                            );

                        longitude.value =
                            String(
                                position.coords.longitude
                            );

                        accuracy.value =
                            position.coords.accuracy == null
                                ? ''
                                : String(
                                    position.coords.accuracy
                                );

                        refreshLocationState();

                        captureButton.disabled =
                            false;

                        captureButton.textContent =
                            'Refresh Current Location';
                    },
                    (error) => {
                        status.textContent =
                            error.code === 1
                                ? 'Location permission was denied. Allow location access and try again.'
                                : 'Unable to capture the current location. Try again or contact an emergency hotline directly.';

                        status.className =
                            'mt-1 text-xs font-bold leading-5 text-red-700';

                        captureButton.disabled =
                            false;

                        captureButton.textContent =
                            'Try Location Again';
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 15000,
                    }
                );
            }

            captureButton?.addEventListener(
                'click',
                captureLocation
            );

            form?.addEventListener(
                'submit',
                (event) => {
                    if (!hasLocation()) {
                        event.preventDefault();

                        status.textContent =
                            'Capture your current location before sending the distress signal.';

                        status.className =
                            'mt-1 text-xs font-bold leading-5 text-red-700';

                        return;
                    }

                    sendButton.disabled =
                        true;

                    sendButton.textContent =
                        'Sending Distress Signal...';
                }
            );

            refreshLocationState();
        })();
    </script>
</x-layouts.auth>
