<?php
/*
 * AppNtpController.php
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
 * @copyright  2022 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Table;

use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LibreNMS\Util\Url;

class AppNtpController extends SimpleTableController
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->validate($request, self::$base_rules);

        $query = \App\Models\Application::with('device')
            ->whereNotNull('data')
            ->where('app_type', 'ntp');

        $this->search($request->get('searchPhrase'), $query);
        $this->filter($request, $query);
        $this->sort($request, $query);

        $limit = $request->get('rowCount', 25);
        $page = $request->get('current', 1);
        $query->limit($limit)->offset(($page - 1) * $limit);

        $rows = $query->get()->map([$this, 'formatItem'])->flatten(1);
        return $this->formatResponse(
            $rows,
            $page,
            $rows->count(),
            $query->select(DB::raw('SUM(JSON_LENGTH(data)) as total'))->value('total')
        );
    }


    protected function sort(Request $request, Builder $query): void
    {
    }

    protected function filter(Request $request, Builder $query): void
    {
        if ($request->get('view') == 'error') {
            $query->whereJsonContains('data', ['error' => 2]);
        }
    }

    protected function search(?string $search, Builder $query): void
    {
        if ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->orWhereHas('device', function (Builder $query) use ($search) {
                    $query->where('hostname', 'LIKE', "%SEARCH%");
                })
                    ->orWhereJsonContains('data', ['peer' => $search])
                    ->orWhereJsonContains('data', ['stratum' => $search])
                    ->orWhereJsonContains('data', ['error' => $search]);
            });
        }
    }

    public function formatItem(Application $app): array
    {
        $device = Url::deviceLink($app->device);

        return array_map(function ($peer) use ($device) {
            return [
                'device' => $device,
                'peer' => $peer['peer'],
                'stratum' => $peer['stratum'],
                'error' => $peer['error'],
            ];
        }, $app->data);
    }
}
