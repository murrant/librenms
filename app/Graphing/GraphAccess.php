<?php

/**
 * GraphAccess.php
 *
 * Who a graph is being rendered for. Either a user, whose permissions are
 * checked, or a trusted context that was authenticated some other way
 * (signed url, allow_unauth_graphs, alert transports).
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
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Graphing;

use App\Graphing\Exceptions\GraphUnauthorized;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final readonly class GraphAccess
{
    /** Request attribute set by AuthenticateGraph when it lets a guest through */
    public const REQUEST_ATTRIBUTE = 'graph_trust';

    public const SIGNED_URL = 'signed-url';
    public const UNAUTH_GRAPHS = 'unauth-graphs';
    public const ALERT = 'alert';

    private function __construct(
        public ?User $user,
        public ?string $trust,
    ) {
    }

    public static function user(User $user): self
    {
        return new self($user, null);
    }

    /**
     * Only use for contexts that have already been authenticated by other means.
     */
    public static function trusted(string $reason): self
    {
        return new self(null, $reason);
    }

    /**
     * Access for the currently logged in user.
     *
     * @throws GraphUnauthorized
     */
    public static function current(): self
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new GraphUnauthorized('Not logged in');
        }

        return self::user($user);
    }

    /**
     * @throws GraphUnauthorized
     */
    public static function fromRequest(Request $request): self
    {
        $user = $request->user();
        if ($user instanceof User) {
            return self::user($user);
        }

        $trust = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (is_string($trust) && $trust !== '') {
            return self::trusted($trust);
        }

        throw new GraphUnauthorized('Not logged in');
    }

    public function isTrusted(): bool
    {
        return $this->trust !== null;
    }
}
