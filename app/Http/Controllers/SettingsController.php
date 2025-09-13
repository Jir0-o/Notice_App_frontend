<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->latest()->get();
        $permissions = Permission::all();
        $users = User::with('roles')->latest()->get();
        return view('backend.pages.settings', compact(['roles','permissions','users']));
    }
}
