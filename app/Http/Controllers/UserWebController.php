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

        return view('users.index', compact('users'));
    }
}