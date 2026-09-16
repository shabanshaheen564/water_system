<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserWebController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')
            ->orderBy('name')
            ->paginate(20);

        return view('users.index', [
            'users' => $users,
            'title' => __('Users'),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => $this->assignableRoles(),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
            'title' => __('Create User'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roles = $validated['roles'] ?? [];
        $directPermissionIds = collect($validated['permissions'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if (! $this->canAssignRoles($roles)) {
            abort(403, __('Insufficient permissions to assign one or more selected roles.'));
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles($roles);

        if (! $user->hasRole('System Owner')) {
            $permissions = Permission::whereIn('id', $directPermissionIds)
                ->where('guard_name', 'web')
                ->get();
            $user->syncPermissions($permissions);
        }

        return redirect()->route('users.index')
            ->with('success', __('User created successfully.'));
    }

    public function show(User $user): View
    {
        $user->load('roles.permissions', 'permissions');

        return view('users.show', [
            'user' => $user,
            'title' => __('User Details'),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load('roles', 'permissions');

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
            'title' => __('Edit User'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $roles = $validated['roles'] ?? [];
        $directPermissionIds = collect($validated['permissions'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $wasSystemOwner = $user->hasRole('System Owner');
        $willBeActive = (bool) $validated['is_active'];

        if (! $this->canAssignRoles($roles)) {
            abort(403, __('Insufficient permissions to assign one or more selected roles.'));
        }

        if ($wasSystemOwner && ! auth()->user()->hasRole('System Owner')) {
            abort(403, __('The System Owner account is protected.'));
        }

        if ($wasSystemOwner && ! in_array('System Owner', $roles, true)) {
            $activeSystemOwners = User::role('System Owner')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSystemOwners === 0) {
                return back()->withErrors([
                    'roles' => __('Cannot remove the last active System Owner role.'),
                ])->withInput();
            }
        }

        if ($wasSystemOwner && ! $willBeActive) {
            $activeSystemOwners = User::role('System Owner')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSystemOwners === 0) {
                return back()->withErrors([
                    'is_active' => __('Cannot deactivate the last active System Owner.'),
                ])->withInput();
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $willBeActive,
        ]);

        $user->syncRoles($roles);

        if (! $wasSystemOwner) {
            $permissions = Permission::whereIn('id', $directPermissionIds)
                ->where('guard_name', 'web')
                ->get();
            $user->syncPermissions($permissions);
        }

        return redirect()->route('users.index')
            ->with('success', __('User updated successfully.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('System Owner')) {
            return back()->withErrors([
                'delete' => __('System Owner users cannot be deleted.'),
            ]);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'delete' => __('You cannot delete your own account.'),
            ]);
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', __('User deleted successfully.'));
    }

    private function assignableRoles()
    {
        $roles = Role::orderBy('name');

        if (auth()->user()->hasRole('Admin') && ! auth()->user()->hasRole('System Owner')) {
            $roles->where('name', '!=', 'System Owner');
        }

        return $roles->get();
    }

    private function canAssignRoles(array $roles): bool
    {
        if (auth()->user()->hasRole('System Owner')) {
            return true;
        }

        if (auth()->user()->hasRole('Admin')) {
            return ! in_array('System Owner', $roles, true);
        }

        return false;
    }
}
