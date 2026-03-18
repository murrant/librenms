<?php

namespace App\Policies;

use App\Facades\Permissions;
use App\Models\AlertRule;
use App\Models\User;


class AlertRulePolicy
{
    use ChecksGlobalPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasGlobalPermission($user, 'viewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AlertRule $alertRule): bool
    {
        if ($this->hasGlobalPermission($user, 'viewAny')) {
            return true;
        }

        if (! $this->hasGlobalPermission($user, 'view')) {
            return false;
        }

        foreach ($alertRule->devices()->pluck('device_id') as $deviceId) {
            if (Permissions::canAccessDevice($deviceId, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, AlertRule $alertRule): bool
    {
        if (! $this->hasGlobalPermission($user, 'create')) {
            return false;
        }

        foreach ($alertRule->devices()->pluck('device_id') as $deviceId) {
            if (Permissions::canAccessDevice($deviceId, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AlertRule $alertRule): bool
    {
        if (! $this->hasGlobalPermission($user, 'update')) {
            return false;
        }

        foreach ($alertRule->devices()->pluck('device_id') as $deviceId) {
            if (Permissions::canAccessDevice($deviceId, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AlertRule $alertRule): bool
    {
        if (! $this->hasGlobalPermission($user, 'delete')) {
            return false;
        }

        foreach ($alertRule->devices()->pluck('device_id') as $deviceId) {
            if (Permissions::canAccessDevice($deviceId, $user)) {
                return true;
            }
        }
    }
}
