<?php

namespace LibreNMS\Enum;

enum SnmpError: string
{
    case Timeout = 'timeout';
    case AuthenticationFailure = 'authentication_failure';
    case UnsupportedProtocol = 'unsupported_protocol';
    case NoSuchInstance = 'no_such_instance';
    case NoSuchObject = 'no_such_object';
    case NoSuchName = 'no_such_name';
    case EndOfMib = 'end_of_mib';
    case OidNotIncreasing = 'oid_not_increasing';
    case EmptyResponse = 'empty_response';
    case GeneralError = 'general_error';

    public function isFatal(): bool
    {
        return match ($this) {
            self::Timeout, self::AuthenticationFailure, self::UnsupportedProtocol, self::GeneralError => true,
            default => false,
        };
    }

    public function isNotFound(): bool
    {
        return match ($this) {
            self::NoSuchInstance, self::NoSuchObject, self::NoSuchName, self::EndOfMib => true,
            default => false,
        };
    }
}
