<?php
/**
 * AlertTransportGroup.php
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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertTransportGroup extends Model
{
    protected $primaryKey = 'transport_group_id';
    public $timestamps = false;

    // ---- Define Relationships ----

    public function transports()
    {
        return $this->belongsToMany('\App\Models\AlertTransport', 'transport_group_transport', 'transport_group_id', 'transport_id');
    }
}
