<?php

namespace App\Policies;

use App\Models\InventoryLot;
use App\Models\User;

class InventoryLotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, InventoryLot $inventoryLot): bool
    {
        return $user->can('inventory.view');
    }

    public function update(User $user, InventoryLot $inventoryLot): bool
    {
        return $user->can('inventory.update');
    }

    public function adjust(User $user, InventoryLot $inventoryLot): bool
    {
        return $user->can('inventory.adjust');
    }

    public function release(User $user, InventoryLot $inventoryLot): bool
    {
        return $user->can('inventory.release');
    }
}
