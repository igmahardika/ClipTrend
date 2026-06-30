<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')->withCount(['projects', 'renderedVideos'])->latest()->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->load('roles', 'projects');
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user->load('roles'), 'roles' => Role::all()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'upload_limit_mb' => ['required', 'integer', 'min:100', 'max:51200'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);
        $user->update([
            'status' => $data['status'],
            'upload_limit_mb' => $data['upload_limit_mb'],
        ]);
        $user->roles()->sync($data['roles'] ?? []);
        return back()->with('success', 'User berhasil diperbarui.');
    }
}
