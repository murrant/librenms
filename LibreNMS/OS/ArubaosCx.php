<?php

namespace LibreNMS\OS;

use LibreNMS\OS;

class ArubaosCx extends OS
{
    protected function __construct(array &$device)
    {
        parent::__construct($device);

        $this->entityVendorTypeMib = 'ARUBAWIRED-NETWORKING-OID';
    }
}
