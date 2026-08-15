<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class DevSessionController extends Controller
{
    public function create(): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);

        $query = User::query()
            ->whereRaw('LOWER(TRIM(role)) = ?', ['admin'])
            ->orderBy('id');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('users', 'status')) {
            $query->whereRaw(
                'LOWER(TRIM(status)) = ?',
                ['active']
            );
        }

        $user = $query->first();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                    'Development login bypass could not find an active Admin account.',
            ], 503);
        }

        $user->tokens()
            ->where('name', 'tabangnow-flutter-local-dev')
            ->delete();

        $token = $user->createToken(
            'tabangnow-flutter-local-dev',
            ['mobile']
        );

        return response()
            ->json([
                'message' => 'Local development session created.',
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => data_get($user, 'username'),
                    'email' => $user->email,
                    'role' => $user->role,
                    'barangay_id' => data_get($user, 'barangay_id'),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
