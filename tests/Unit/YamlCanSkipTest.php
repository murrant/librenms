<?php
/*
 * YamlCanSkipTest.php
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
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Tests\Unit;

use LibreNMS\Device\YamlDiscovery;

class YamlCanSkipTest extends \LibreNMS\Tests\TestCase
{
    public function testCanSkipIndex()
    {
        // Simple index tests
//        $this->assertFalse(YamlDiscovery::canSkipItem(null, '1', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '!=',
//                'value' => 1,
//            ]],
//        ], []), 'failed != single index match');
//        $this->assertTrue(YamlDiscovery::canSkipItem(null, '2', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '!=',
//                'value' => 1,
//            ]],
//        ], []), 'failed != single index skip');
//        $this->assertTrue(YamlDiscovery::canSkipItem(null, '1', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '=',
//                'value' => 1,
//            ]],
//        ], []), 'failed == single index skip');
//        $this->assertFalse(YamlDiscovery::canSkipItem(null, '2', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '=',
//                'value' => 1,
//            ]],
//        ], []), 'failed = single index match');
//
//        // complex
//        $this->assertTrue(YamlDiscovery::canSkipItem(null, '1.2.3', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '==',
//                'value' => [1, 2, 3],
//            ]],
//        ], []), 'failed == multi index match');
//        $this->assertFalse(YamlDiscovery::canSkipItem(null, '1.2.4', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '=',
//                'value' => [1, 2, 3],
//            ]],
//        ], []), 'failed == multi index mismatch');
//        $this->assertTrue(YamlDiscovery::canSkipItem(null, '1.2.3', [
//            'skip_values' => [[
//                'oid' => 'index',
//                'op'=> '=',
//                'value' => [1, null, 3],
//            ]],
//        ], []), 'failed == multi partial index match');
        $this->assertTrue(YamlDiscovery::canSkipItem(null, '1.2.3', [
            'skip_values' => [[
                'oid' => 'index',
                'op'=> '=',
                'value' => [1],
            ]],
        ], []), 'failed == multi partial index mismatch index match');

        $this->assertFalse(YamlDiscovery::canSkipItem(null, '1.2.3', [
            'skip_values' => [[
                'oid' => 'index',
                'op'=> '!=',
                'value' => [1, 2, 3],
            ]],
        ], []), 'failed != multi index match');
        $this->assertTrue(YamlDiscovery::canSkipItem(null, '1.2.4', [
            'skip_values' => [[
                'oid' => 'index',
                'op'=> '!=',
                'value' => [1, 2, 3],
            ]],
        ], []), 'failed != multi index skip');
        $this->assertFalse(YamlDiscovery::canSkipItem(null, '1.2.3', [
            'skip_values' => [[
                'oid' => 'index',
                'op'=> '!=',
                'value' => [1, null, 3],
            ]],
        ], []), 'failed != multi partial index match');
        $this->assertFalse(YamlDiscovery::canSkipItem(null, '1.2.3', [
            'skip_values' => [[
                'oid' => 'index',
                'op'=> '!=',
                'value' => [1],
            ]],
        ], []), 'failed != multi partial index mismatch index match');
    }
}
