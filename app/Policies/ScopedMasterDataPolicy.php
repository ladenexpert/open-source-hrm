<?php

namespace App\Policies;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class ScopedMasterDataPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function view(Employee $user, Model $record): bool
    {
        return $this->canViewScopedMasterDataRecord($user, $record);
    }

    public function create(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }

    public function update(Employee $user, Model $record): bool
    {
        return $this->canManageScopedMasterDataRecord($user, $record);
    }

    public function delete(Employee $user, Model $record): bool
    {
        return $this->canManageScopedMasterDataRecord($user, $record);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->canManageHrMasterData($user);
    }
}
