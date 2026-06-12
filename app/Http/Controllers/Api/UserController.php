<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListQueryRequest;
use App\Models\User;
use App\Support\RoleAssignmentGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(ListQueryRequest $request)
    {
        $this->authorize('viewAny', User::class);
        $request->validated();

        $users = User::with('roles:id,name', 'permissions:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($request->perPage(20));

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! empty($data['role_ids'])) {
            $this->authorize('assignRoles', $user);
            $roleIds = RoleAssignmentGuard::assertAssignable($request->user(), $data['role_ids']);
            $user->assignRole($roleIds);
        }

        return response()->json($user->load('roles:id,name', 'permissions:id,name'), 201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return response()->json($user->load('roles:id,name', 'permissions:id,name'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $this->authorize('update', $user);

        if (array_key_exists('role_ids', $data) && $data['role_ids'] !== null) {
            $this->authorize('assignRoles', $user);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update(array_filter($data, fn ($key) => $key !== 'role_ids', ARRAY_FILTER_USE_KEY));

        if (isset($data['role_ids'])) {
            $roleIds = RoleAssignmentGuard::assertAssignable($request->user(), $data['role_ids']);
            $user->syncRoles($roleIds);
        }

        return response()->json($user->load('roles:id,name', 'permissions:id,name'));
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الخاص'], 403);
        }

        $user->delete();

        return response()->noContent();
    }
}
