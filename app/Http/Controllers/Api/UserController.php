<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteUsersRequest;
use App\Http\Requests\BulkUpdateUserStatusRequest;
use App\Http\Requests\ListQueryRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserStatusService;
use App\Support\RoleAssignmentGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly UserStatusService $statusService) {}

    public function index(ListQueryRequest $request)
    {
        $this->authorize('viewAny', User::class);
        $request->validated();

        $users = User::with('roles:id,name', 'permissions:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($request->perPage(20));

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
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

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (array_key_exists('role_ids', $data) && $data['role_ids'] !== null) {
            $this->authorize('assignRoles', $user);
        }

        if (($data['status'] ?? null) === User::STATUS_INACTIVE && $user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $wasActive = $user->isActive();
        $user->update(array_filter($data, fn ($key) => $key !== 'role_ids', ARRAY_FILTER_USE_KEY));

        if ($wasActive && ! $user->isActive()) {
            $this->statusService->revokeTokens([$user->id]);
        }

        if (isset($data['role_ids'])) {
            $roleIds = RoleAssignmentGuard::assertAssignable($request->user(), $data['role_ids']);
            $user->syncRoles($roleIds);
        }

        return response()->json($user->load('roles:id,name', 'permissions:id,name'));
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->noContent();
    }

    public function bulkDelete(BulkDeleteUsersRequest $request)
    {
        $ids = array_values(array_diff($request->validated('ids'), [$request->user()->id]));
        // Model deletes (not a query delete) so Spatie detaches roles and permissions.
        $users = $ids === [] ? collect() : User::whereIn('id', $ids)->get();
        DB::transaction(fn () => $users->each->delete());
        $count = $users->count();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' users',
        ]);
    }

    public function bulkStatus(BulkUpdateUserStatusRequest $request)
    {
        $data = $request->validated();
        $count = $this->statusService->setStatus($data['ids'], $data['status'], $request->user()->id);

        return response()->json([
            'updated' => $count,
            'status' => $data['status'],
            'message' => 'Updated '.$count.' users',
        ]);
    }
}
