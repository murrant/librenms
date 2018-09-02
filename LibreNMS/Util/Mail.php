<?php
/**
 * Mail.php
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

namespace LibreNMS\Util;

use LibreNMS\Config;

class Mail
{
    /**
     * Parse string with emails (separated by semi-colins or commas).
     *
     * @param string $emails
     * @return array with email and name fields
     */
    public static function parseEmails($emails)
    {
        $result = [];
        $email_regex = '/^[\"\']?([^\"\']+)[\"\']?\s{0,}<([^@]+@[^>]+)>$/';
        if (is_string($emails)) {
            $emails = preg_split('/[,;]\s{0,}/', $emails);
            foreach ($emails as $email) {
                if (preg_match($email_regex, $email, $out, PREG_OFFSET_CAPTURE)) {
                    $result[] = ['email' => $out[2][0], 'name' => $out[1][0]];
                } elseif (str_contains($email, '@')) {
                    $result[] = ['email' => $email, 'name' => Config::get('email_user', 'librenms')];
                }
            }
        }

        return $result;
    }
}
