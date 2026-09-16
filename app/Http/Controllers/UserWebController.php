<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
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
            'roles' => Role::orderBy('name')->get(),
            'title' => __('Create User'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        return redirect()->route('users.index')
            ->with('success', __('User created successfully.'));
    }

    public function show(User $user): View
    {
        $user->load('roles.permissions');

        return view('users.show', [
            'user' => $user,
            'title' => __('User Details'),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'title' => __('Edit User'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $wasSystemOwner = $user->hasRole('System Owner');
        $willBeActive = (bool) $validated['is_active'];

        if ($wasSystemOwner && ! $willBeActive) {
            $activeSystemOwners = User::role('System Owner')
                ->where('is_active', true)
                ->whereKeyNot($user->id)
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

        $user->syncRoles($validated['roles'] ?? []);

        return redirect()->route('users.index')
            ->with('success', __('User updated successfully.'));
    }
}
