<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionWebController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::with('roles')
            ->orderBy('name')
            ->get();

        return view('permissions.index', [
            'permissions' => $permissions,
            'title' => __('Permissions'),
        ]);
    }
}
