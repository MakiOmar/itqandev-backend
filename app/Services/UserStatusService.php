<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Activating / deactivating accounts. Deactivation revokes API tokens so the
 * user is signed out immediately instead of at token expiry.
 */
class UserStatusService
{
    /**
     * @param  array<int, int>  $ids
     * @return int Number of users whose status was written
     */
    public function setStatus(array $ids, string $status, ?int $actingUserId = null): int
    {
        // Never let an admin lock themselves out through a bulk action.
        if ($status === User::STATUS_INACTIVE && $actingUserId !== null) {
            $ids = array_values(array_diff($ids, [$actingUserId]));
        }

        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $status) {
            $count = User::whereIn('id', $ids)->update(['status' => $status]);

            if ($status === User::STATUS_INACTIVE) {
                $this->revokeTokens($ids);
            }

            return $count;
        });
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function revokeTokens(array $ids): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->delete();
    }
}
