<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    private function menus(User $user): MenuPolicy
    {
        return app(MenuPolicy::class);
    }

    public function viewAny(User $user): bool
    {
        return $this->menus($user)->viewAny($user);
    }

    public function view(User $user, MenuItem $menuItem): bool
    {
        return $this->menus($user)->view($user, $menuItem->menu);
    }

    public function create(User $user): bool
    {
        return $this->menus($user)->create($user);
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $this->menus($user)->update($user, $menuItem->menu);
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $this->menus($user)->delete($user, $menuItem->menu);
    }
}
