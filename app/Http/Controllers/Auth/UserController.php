<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\SyncUserRolesRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')->paginate();

        $data = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values(),
            ];
        });

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $users->currentPage(),
                'from' => $users->firstItem(),
                'last_page' => $users->lastPage(),
                'path' => $users->path(),
                'per_page' => $users->perPage(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'roles' => $user->roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $roles = $request->input('roles', []);

            if (! $this->canAssignRoles($roles)) {
                return response()->json([
                    'message' => 'Insufficient permissions to assign one or more selected roles.',
                ], 403);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $user->syncRoles($roles);
            $user->load('roles');

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values(),
            ], 201);
        });
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $roles = $validated['roles'] ?? [];

        if (! $this->canAssignRoles($roles)) {
            return response()->json([
                'message' => 'Insufficient permissions to assign one or more selected roles.',
            ], 403);
        }

        if ($user->hasRole('System Owner') && ! in_array('System Owner', $roles, true)) {
            $activeSystemOwners = User::role('System Owner')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSystemOwners === 0) {
                return response()->json([
                    'message' => 'Cannot remove the last active System Owner role.',
                ], 422);
            }
        }

        unset($validated['password'], $validated['password_confirmation']);

        $user->update(array_diff_key($validated, array_flip(['roles'])));
        $user->syncRoles($roles);
        $user->load('roles');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'roles' => $user->roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
        ]);
    }

    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $roles = $request->input('roles', []);

        if (! $this->canAssignRoles($roles)) {
            return response()->json([
                'message' => 'Insufficient permissions to assign one or more selected roles.',
            ], 403);
        }

        if ($user->hasRole('System Owner') && ! in_array('System Owner', $roles, true)) {
            $activeSystemOwners = User::role('System Owner')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSystemOwners === 0) {
                return response()->json([
                    'message' => 'Cannot remove the last active System Owner role.',
                ], 422);
            }
        }

        $user->syncRoles($roles);
        $user->load('roles');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'roles' => $user->roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $isActive = $request->boolean('is_active');

        if (! $isActive && $user->hasRole('System Owner')) {
            $activeSystemOwners = User::role('System Owner')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSystemOwners === 0) {
                return response()->json([
                    'message' => 'Cannot deactivate the last active System Owner.',
                ], 422);
            }
        }

        $user->update([
            'is_active' => $isActive,
        ]);

        $user->refresh();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'roles' => $user->roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
        ]);
    }

    private function canAssignRoles(array $roles): bool
    {
        $currentUser = request()->user();

        if ($currentUser->hasRole('System Owner')) {
            return true;
        }

        if ($currentUser->hasRole('Admin')) {
            return ! in_array('System Owner', $roles, true);
        }

        return false;
    }
}
