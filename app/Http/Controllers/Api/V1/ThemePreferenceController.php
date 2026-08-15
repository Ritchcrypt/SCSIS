<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ThemePreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401, 'Authentication is required.');

        $this->ensureThemeColumnsExist();

        $mode = strtolower(trim((string) ($user->theme_mode ?: 'system')));

        if ($mode === 'white') {
            $mode = 'light';
        }

        if (! in_array($mode, ['light', 'dark', 'system', 'custom'], true)) {
            $mode = 'system';
        }

        if ($mode === 'custom' && $user->role !== 'admin') {
            $mode = 'system';
        }

        $customColor = $this->validColor($user->theme_custom_color)
            ? strtolower((string) $user->theme_custom_color)
            : '#2563eb';

        return response()
            ->json([
                'data' => [
                    'theme_mode' => $mode,
                    'theme_custom_color' => $customColor,
                    'allowed_modes' => $this->allowedModesFor($user->role),
                    'is_admin' => $user->role === 'admin',
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401, 'Authentication is required.');

        $this->ensureThemeColumnsExist();

        $submittedMode = strtolower(
            trim((string) $request->input('theme_mode'))
        );

        if ($submittedMode === 'white') {
            $submittedMode = 'light';
        }

        $request->merge([
            'theme_mode' => $submittedMode,
        ]);

        $validated = $request->validate(
            [
                'theme_mode' => [
                    'required',
                    'string',
                    Rule::in($this->allowedModesFor($user->role)),
                ],
                'theme_custom_color' => [
                    'nullable',
                    'string',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
            ],
            [
                'theme_mode.in' => 'Invalid theme mode selected.',
                'theme_custom_color.regex' =>
                    'Custom color must use a valid HEX value.',
            ]
        );

        $themeMode = $validated['theme_mode'];
        $customColor = null;

        if ($themeMode === 'custom') {
            $customColor = $validated['theme_custom_color'] ?? null;

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

        return response()
            ->json([
                'message' => 'Theme preference updated.',
                'data' => [
                    'theme_mode' => $themeMode,
                    'theme_custom_color' => $customColor,
                    'allowed_modes' => $this->allowedModesFor($user->role),
                    'is_admin' => $user->role === 'admin',
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function allowedModesFor(string $role): array
    {
        $modes = [
            'light',
            'dark',
            'system',
        ];

        if (strtolower(trim($role)) === 'admin') {
            $modes[] = 'custom';
        }

        return $modes;
    }

    private function ensureThemeColumnsExist(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'theme_mode')
            || ! Schema::hasColumn('users', 'theme_custom_color')
        ) {
            throw ValidationException::withMessages([
                'theme_mode' => 'Theme settings are not available.',
            ]);
        }
    }

    private function validColor(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }
}
