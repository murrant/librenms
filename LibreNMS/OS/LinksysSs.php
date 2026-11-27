<?php

namespace LibreNMS\OS;

class LinksysSs extends Generic
{
    protected function collectPortPower(array $ports): array
    {
        $result = [];
        foreach ($this->portTable(['LINKSYS-POE-MIB::rlPethPsePortPowerLimit', 'LINKSYS-POE-MIB::rlPethPsePortOutputPower']) as $key => $row) {
            $index = substr(strrchr($key, '.'), 1);
            if (isset($ports[$index], $row['rlPethPsePortOutputPower'])) {
                $limit = $row['rlPethPsePortPowerLimit'] ?? null;
                $result[$index] = $this->portPowerFields($limit, $limit, $row['rlPethPsePortOutputPower'], $limit);
            }
        }

        return $result;
    }
}
