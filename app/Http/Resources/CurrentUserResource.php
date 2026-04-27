<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Session /me + login user payload: roles, Spatie permissions, primary role for clients.
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['roles', 'permissions']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ])->values(),
            'role' => $this->roles->first()?->name ?? 'user',
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
