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
            $username = $validated['username'] ?? $this->generateUsername($validated['email'], $validated['name']);

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

    private function generateUsername(string $email, string $name): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '-')
            ->trim('-._')
            ->toString();

        if ($base === '') {
            $base = Str::of($name)
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '-')
                ->trim('-')
                ->toString();
        }

        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        $suffix = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix++;
        }

        return $candidate;
    }
}
