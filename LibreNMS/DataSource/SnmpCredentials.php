<?php
/**
 * SnmpCredentials.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\DataSource;

use LibreNMS\Interfaces\DataSource\Credentials;

class SnmpCredentials implements Credentials
{
    protected $data;

    protected function __construct($data)
    {
        $this->data = $data;
    }

    public function serialize()
    {
        return serialize($this->data);
    }

    public function unserialize($serialized)
    {
        $this->data = unserialize($serialized);
    }

    /**
     * Get the type of this credential, should be a short string that matches the database field
     *
     * @return mixed
     */
    public function type()
    {
        return 'snmp';
    }

    /**
     * Get the stored credentials as an array of data
     *
     * @return array
     */
    public function get()
    {
        return $this->data;
    }

    /**
     * The string output should be a string ready to be used by the data source
     * such as "-v2c -c public" for snmp v2
     *
     * @return string
     */
    public function __toString()
    {
        if (isset($this->data['community'])) {
            return '-c ' . $this->data['community'];
        } elseif(isset($this->data['authlevel'])) {
            $level = $this->data['authlevel'];
            $name = !empty($this->data['authname']) ? $this->data['authname'] : 'root';
            $context = !empty($this->data['context']) ? $this->data['context'] : '';

            $str = "-l '$level'";
            $str .= " -n '$context'";
            $str .= " -u '$name'";

            if ($level === 'noAuthNoPriv') {
                // We have to provide a username anyway (see Net-SNMP doc)
                return $str;
            }

            $str .= " -a '{$this->data['authalgo']}'";
            $str .= " -A '{$this->data['authpass']}'";

            if ($level === 'authNoPriv') {
                return $str;
            }

            if ($level === 'authPriv') {
                $str .= " -x '{$this->data['cryptoalgo']}'";
                $str .= " -X '{$this->data['cryptopass']}'";
                return $str;
            }
        }

        return '';
    }
}
