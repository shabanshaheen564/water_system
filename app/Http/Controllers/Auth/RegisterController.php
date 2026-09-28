<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        $targetRole = Role::findOrFail($validated['role_id']);

        if (! $this->canAssignRole($currentUser, $targetRole)) {
            return response()->json([
                'message' => 'Insufficient permissions to assign this role',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $targetRole) {
            $base = Str::lower(Str::before($validated['email'], '@'));
            $base = (string) Str::of($base)->replaceMatches('/[^A-Za-z0-9._-]+/', '')->substr(0, 80);
            $base = strlen($base) >= 3 ? $base : 'user';
            $username = $validated['username'] ?? $base;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $counter++;
                $username = substr($base, 0, 90 - strlen((string) $counter)) . $counter;
            }

            $user = User::create([
                'name' => $validated['name'],
                'username' => $username,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'last_login_at' => null,
            ]);

            $user->assignRole($targetRole);
            $user->refresh();

            return response()->json([
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'role' => [
                        'id' => $targetRole->id,
                        'name' => $targetRole->name,
                    ],
                ],
            ], 201);
        });
    }

    private function canAssignRole(User $currentUser, Role $targetRole): bool
    {
        if ($currentUser->hasRole('System Owner')) {
            return true;
        }

        if ($currentUser->hasRole('Admin')) {
            return $targetRole->name !== 'System Owner';
        }

        return false;
    }
}