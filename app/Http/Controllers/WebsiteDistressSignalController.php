<?php

namespace App\Http\Controllers;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebsiteDistressSignalController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        return view('distress-signal.create', [
            'registeredUser' =>
                $user instanceof User
                    ? $user
                    : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $contactNumber = (string) preg_replace(
            '/[\s\-\(\)]+/',
            '',
            trim(
                (string) $request->input(
                    'contact_number'
                )
            )
        );

        if (
            Str::startsWith(
                $contactNumber,
                '+63'
            )
        ) {
            $contactNumber =
                '0'
                . substr(
                    $contactNumber,
                    3
                );
        }

        $submittedName = (string) preg_replace(
            '/\s+/u',
            ' ',
            trim(
                (string) $request->input(
                    'reporter_name'
                )
            )
        );

        $reporterName =
            $user instanceof User
                ? trim(
                    (string) $user->name
                )
                : $submittedName;

        $request->merge([
            'reporter_name' =>
                $reporterName,

            'contact_number' =>
                $contactNumber,

            'emergency_details' =>
                trim(
                    (string) $request->input(
                        'emergency_details'
                    )
                ),
        ]);

        $reporterRules =
            $user instanceof User
                ? [
                    'required',
                    'string',
                    'max:255',
                ]
                : [
                    'required',
                    'string',
                    'min:3',
                    'max:255',
                    function (
                        string $attribute,
                        mixed $value,
                        \Closure $fail
                    ): void {
                        $name = (string) preg_replace(
                            '/\s+/u',
                            ' ',
                            trim(
                                (string) $value
                            )
                        );

                        $parts = array_values(
                            array_filter(
                                preg_split(
                                    '/\s+/u',
                                    $name
                                ) ?: []
                            )
                        );

                        if (
                            count($parts) < 2
                        ) {
                            $fail(
                                'Enter the reporter\'s complete name, including at least a first name and last name.'
                            );

                            return;
                        }

                        foreach (
                            $parts
                            as $part
                        ) {
                            if (
                                preg_match(
                                    '/\p{L}/u',
                                    $part
                                ) !== 1
                            ) {
                                $fail(
                                    'Enter a valid complete reporter name.'
                                );

                                return;
                            }
                        }
                    },
                ];

        $validated =
            $request->validate(
                [
                    'reporter_name' =>
                        $reporterRules,

                    'contact_number' => [
                        'required',
                        'string',
                        'regex:/^09\d{9}$/',
                    ],

                    'emergency_details' => [
                        'required',
                        'string',
                        'min:3',
                        'max:1000',
                    ],

                    'latitude' => [
                        'required',
                        'numeric',
                        'between:-90,90',
                    ],

                    'longitude' => [
                        'required',
                        'numeric',
                        'between:-180,180',
                    ],

                    'accuracy_meters' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999.99',
                    ],
                ],
                [
                    'reporter_name.required' =>
                        'Reporter full name is required.',

                    'contact_number.required' =>
                        'Mobile number is required.',

                    'contact_number.regex' =>
                        'Enter a valid Philippine mobile number, such as 09123456789.',

                    'emergency_details.required' =>
                        'Describe the emergency before sending the distress signal.',

                    'emergency_details.min' =>
                        'Emergency details must contain at least 3 characters.',

                    'latitude.required' =>
                        'Capture your current location before sending the distress signal.',

                    'longitude.required' =>
                        'Capture your current location before sending the distress signal.',
                ]
            );

        $identityKey =
            $user instanceof User
                ? 'user:'
                    . (int) $user->id
                : 'guest:'
                    . hash(
                        'sha256',
                        (string) (
                            $request->ip()
                            ?? 'unknown'
                        )
                    );

        $rateKey =
            'website-sos|'
            . $identityKey;

        if (
            RateLimiter::tooManyAttempts(
                $rateKey,
                6
            )
        ) {
            $seconds =
                RateLimiter::availableIn(
                    $rateKey
                );

            return back()
                ->withInput()
                ->withErrors([
                    'emergency_details' =>
                        "Too many distress signals were submitted recently. Try again in {$seconds} seconds or contact an emergency hotline directly.",
                ]);
        }

        $installationId =
            $user instanceof User
                ? 'website-user-'
                    . (int) $user->id
                : 'website-session-'
                    . substr(
                        hash(
                            'sha256',
                            session()->getId()
                            . '|'
                            . (
                                $request->ip()
                                ?? 'unknown'
                            )
                        ),
                        0,
                        64
                    );

        $requestId =
            'website-'
            . (string) Str::uuid();

        $alert =
            DB::transaction(
                function () use (
                    $request,
                    $validated,
                    $user,
                    $installationId,
                    $requestId
                ): MobileEmergencyAlert {
                    $alert =
                        MobileEmergencyAlert::query()
                            ->create([
                                'alert_code' =>
                                    'TMP-'
                                    . Str::random(
                                        20
                                    ),

                                'device_id' =>
                                    null,

                                'user_id' =>
                                    $user instanceof User
                                        ? (int) $user->id
                                        : null,

                                'reporter_name' =>
                                    $validated[
                                        'reporter_name'
                                    ],

                                'installation_id' =>
                                    $installationId,

                                'request_id' =>
                                    $requestId,

                                'status' =>
                                    'active',

                                'emergency_details' =>
                                    $validated[
                                        'emergency_details'
                                    ],

                                'contact_number' =>
                                    $validated[
                                        'contact_number'
                                    ],

                                'latitude' =>
                                    $validated[
                                        'latitude'
                                    ],

                                'longitude' =>
                                    $validated[
                                        'longitude'
                                    ],

                                'accuracy_meters' =>
                                    $validated[
                                        'accuracy_meters'
                                    ] ?? null,

                                'location_source' =>
                                    'current',

                                'source' =>
                                    'website',

                                'ip_hash' =>
                                    $request->ip()
                                        ? hash(
                                            'sha256',
                                            (string) $request->ip()
                                        )
                                        : null,

                                'user_agent_hash' =>
                                    $request->userAgent()
                                        ? hash(
                                            'sha256',
                                            (string) $request->userAgent()
                                        )
                                        : null,

                                'triggered_at' =>
                                    now(),
                            ]);

                    $alert->forceFill([
                        'alert_code' =>
                            'SOS-'
                            . now()->format(
                                'Ymd'
                            )
                            . '-'
                            . str_pad(
                                (string) $alert->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),
                    ])->save();

                    $recipientIds =
                        User::query()
                            ->whereIn(
                                'role',
                                [
                                    'admin',
                                    'official',
                                    'dao',
                                ]
                            )
                            ->when(
                                Schema::hasColumn(
                                    'users',
                                    'is_active'
                                ),
                                function (
                                    Builder $query
                                ): void {
                                    $query->where(
                                        'is_active',
                                        true
                                    );
                                }
                            )
                            ->pluck(
                                'id'
                            )
                            ->filter()
                            ->unique()
                            ->values();

                    $summary =
                        Str::limit(
                            (string) $alert
                                ->emergency_details,
                            120
                        );

                    foreach (
                        $recipientIds
                        as $recipientId
                    ) {
                        UserNotification::query()
                            ->create([
                                'user_id' =>
                                    (int) $recipientId,

                                'type' =>
                                    'mobile_emergency',

                                'source_id' =>
                                    (int) $alert->id,

                                'title' =>
                                    'URGENT: Website Distress Signal',

                                'message' =>
                                    $alert->alert_code
                                    . ' from '
                                    . $alert->display_name
                                    . ': '
                                    . $summary
                                    . ' Mobile: '
                                    . $alert->contact_number
                                    . '.',

                                'is_read' =>
                                    false,

                                'read_at' =>
                                    null,
                            ]);
                    }

                    return $alert;
                }
            );

        RateLimiter::hit(
            $rateKey,
            60
        );

        return redirect()
            ->route(
                'distress-signal.create'
            )
            ->with(
                'success',
                'Distress signal '
                . $alert->alert_code
                . ' was sent successfully. Responders have been notified.'
            );
    }
}
