<?php

namespace LibreNMS\Enum;

enum DeviceStatus
{
    case DISABLED;
    case DOWN;
    case IGNORED_DOWN;
    case IGNORED_UP;
    case NEVER_POLLED;
    case UP;

    public function asSeverity(): Severity
    {
//        if ($device->disabled == 1) {
//            return 'blackbg';
//        } elseif ($device->disable_notify == 1) {
//            return 'blackbg';
//        } elseif ($device->ignore == 1) {
//            return 'label-default';
//        } elseif ($device->status == 0) {
//            return 'label-danger';
//        } else {
//            $warning_time = LibrenmsConfig::get('uptime_warning', 86400);
//            if ($device->uptime < $warning_time && $device->uptime != 0) {
//                return 'label-warning';
//            }
//
//            return 'label-success';
//        }

        return Severity::Unknown;
    }
}
