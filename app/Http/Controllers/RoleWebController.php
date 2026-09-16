<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleWebController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('roles.index', [
            'roles' => $roles,
            'title' => __('Roles'),
        ]);
    }
}
