<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ThemePreferenceController extends Controller
{
    public function update(
        Request $request
    ): JsonResponse|RedirectResponse {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'theme_mode')
            || ! Schema::hasColumn(
                'users',
                'theme_custom_color'
            )
        ) {
            throw ValidationException::withMessages([
                'theme_mode' =>
                    'Theme settings are not available.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize the visible "White" option
        |--------------------------------------------------------------------------
        |
        | The interface may submit "white", while the database and layout use
        | "light". Store only the established "light" value.
        |
        */

        $submittedMode = strtolower(
            trim((string) $request->input('theme_mode'))
        );

        if ($submittedMode === 'white') {
            $submittedMode = 'light';
        }

        $request->merge([
            'theme_mode' => $submittedMode,
        ]);

        $allowedModes = [
            'light',
            'dark',
            'system',
        ];

        if ($user->role === 'admin') {
            $allowedModes[] = 'custom';
        }

        $validated = $request->validate(
            [
                'theme_mode' => [
                    'required',
                    'string',
                    Rule::in($allowedModes),
                ],
                'theme_custom_color' => [
                    'nullable',
                    'string',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
            ],
            [
                'theme_mode.in' =>
                    'Invalid theme mode selected.',
                'theme_custom_color.regex' =>
                    'Custom color must use a valid HEX value.',
            ]
        );

        $themeMode = $validated['theme_mode'];
        $customColor = null;

        if ($themeMode === 'custom') {
            $customColor =
                $validated['theme_custom_color'] ?? null;

            if (! $customColor) {
                throw ValidationException::withMessages([
                    'theme_custom_color' =>
                        'Please select a custom color.',
                ]);
            }

            $customColor = strtolower($customColor);
        }

        $user->forceFill([
            'theme_mode' => $themeMode,
            'theme_custom_color' => $customColor,
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' =>
                    'Theme preference updated.',
                'theme_mode' => $themeMode,
                'theme_custom_color' => $customColor,
            ]);
        }

        return back()->with(
            'success',
            'Theme preference updated.'
        );
    }
}