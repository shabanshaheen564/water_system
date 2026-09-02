<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $roles = $user->roles
            ->sortBy('name')
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values();

        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ]);
    }
}