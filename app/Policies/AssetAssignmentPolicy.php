<?php

namespace App\Policies;

use App\Models\AssetAssignment;
use App\Models\User;

class AssetAssignmentPolicy
{
    public function return(User $user, AssetAssignment $assignment): bool
    {
        return $assignment->isActive() && $user->hasPermission('assignment.return');
    }
}
