<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            $user = User::create([
                'name' => $validated['name'],
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