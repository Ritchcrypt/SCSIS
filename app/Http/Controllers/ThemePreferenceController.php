<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ThemePreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'theme_mode')
            || ! Schema::hasColumn('users', 'theme_custom_color')
        ) {
            return back()->withErrors([
                'theme_mode' => 'Theme settings are not available yet. Please run the latest migrations.',
            ]);
        }

        $allowedModes = ['light', 'dark', 'system'];

        if ($user->role === 'admin') {
            $allowedModes[] = 'custom';
        }

        $validated = $request->validate([
            'theme_mode' => ['required', 'string', Rule::in($allowedModes)],
            'theme_custom_color' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ], [
            'theme_mode.in' => 'Invalid theme mode selected.',
            'theme_custom_color.regex' => 'Custom color must be a valid HEX color like #2563eb.',
        ]);

        $themeMode = $validated['theme_mode'];
        $customColor = null;

        if ($themeMode === 'custom') {
            $customColor = $validated['theme_custom_color'] ?? null;

            if (! $customColor) {
                return back()->withErrors([
                    'theme_custom_color' => 'Please select a custom color.',
                ]);
            }

            $customColor = strtolower($customColor);
        }

        $user->forceFill([
            'theme_mode' => $themeMode,
            'theme_custom_color' => $customColor,
        ])->save();

        return back()->with('success', 'Theme preference updated.');
    }
}