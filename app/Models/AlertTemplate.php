<?php

/**
 * app/Models/AlertTemplate.php
 *
 * Model for access to alert_templates table data
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2018 Neil Lathwood
 * @author     Neil Lathwood <gh+n@laf.io>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertTemplate extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['name', 'template', 'title', 'title_rec'];

    // ---- Define Relationships ----
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\AlertTemplateMap, $this>
     */
    public function map(): HasMany
    {
        return $this->hasMany(AlertTemplateMap::class, 'alert_templates_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\AlertRule, $this>
     */
    public function alert_rules(): BelongsToMany
    {
        return $this->belongsToMany(AlertRule::class, 'alert_template_map', 'alert_templates_id', 'alert_rule_id')
            ->orderBy('alert_rules.name');
    }
}
