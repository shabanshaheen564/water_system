<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

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

    public function show(User $user): View
    {
        $user->load('roles.permissions');

        return view('users.show', [
            'user' => $user,
            'title' => __('User Details'),
        ]);
    }
}
