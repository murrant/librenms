<?php

/**
 * Graph.php
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Util;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LibreNMS\Data\Graphing\GraphFactory;
use LibreNMS\Data\Graphing\GraphImage;
use LibreNMS\Data\Graphing\GraphParameters;
use LibreNMS\Enum\ImageFormat;
use LibreNMS\Exceptions\InvalidGraph;
use LibreNMS\Exceptions\RrdGraphException;
use Rrd;

class Graph
{
    const BASE64_OUTPUT = 1; // BASE64 encoded image data
    const INLINE_BASE64 = 2; // img src inline base64 image
    const IMAGE_PNG = 4; // img src inline base64 image
    const IMAGE_SVG = 8; // img src inline base64 image

    /**
     * Convenience helper to specify desired image output
     *
     * @param  array|string  $vars
     * @param  int  $flags
     * @return string
     */
    public static function getImageData($vars, int $flags = 0): string
    {
        if ($flags & self::IMAGE_PNG) {
            $vars['graph_type'] = 'png';
        }

        if ($flags & self::IMAGE_SVG) {
            $vars['graph_type'] = 'svg';
        }

        if ($flags & self::INLINE_BASE64) {
            return self::getImage($vars)->inline();
        }

        if ($flags & self::BASE64_OUTPUT) {
            return self::getImage($vars)->base64();
        }

        return self::getImage($vars)->data;
    }

    /**
     * Fetch a GraphImage based on the given $vars
     * Catches errors generated and always returns GraphImage
     *
     * @param  array|string  $vars
     * @return GraphImage
     */
    public static function getImage($vars): GraphImage
    {
        try {
            return self::get($vars);
        } catch (RrdGraphException $e) {
            if (Debug::isEnabled()) {
                throw $e;
            }

            return new GraphImage(ImageFormat::forGraph($vars['graph_type'] ?? null), 'Error', $e->generateErrorImage());
        }
    }

    /**
     * Fetch a GraphImage based on the given $vars
     *
     * @param  array|string  $vars
     * @return GraphImage
     *
     * @throws RrdGraphException
     * @throws InvalidGraph
     */
    public static function get(array|string $vars): GraphImage
    {
        // handle possible graph url input
        if (is_string($vars)) {
            $vars = Url::parseLegacyPathVars($vars);
        }

        $graph_params = new GraphParameters($vars);
        app()->instance(GraphParameters::class, $graph_params);
        $name = $graph_params->type . '_' . $graph_params->subtype;

        /** @var GraphFactory $factory */
        $factory = app(GraphFactory::class);
        $graph = $factory->graphFor($name, $vars);

        $rrd_options = self::getRrdOptions($vars, $rrd_filename);

        // Generating the graph!
        try {
            $image_data = Rrd::graph($rrd_options);

            return new GraphImage($graph_params->imageFormat, $graph->getGraphTitle(), $image_data);
        } catch (RrdGraphException $e) {
            // preserve original error if debug is enabled, otherwise make it a little more user friendly
            if (Debug::isEnabled()) {
                throw $e;
            }

            if ($rrd_filename && ! Rrd::checkRrdExists($rrd_filename)) {
                throw new RrdGraphException('No Data file' . basename($rrd_filename), 'No Data', $graph_params->width, $graph_params->height, $e->getCode(), $e->getImage());
            }

            throw new RrdGraphException('Error: ' . $e->getMessage(), 'Draw Error', $graph_params->width, $graph_params->height, $e->getCode(), $e->getImage());
        }
    }

    /**
     * Build RRD options for the given $vars
     *
     * @param  array|string  $vars
     * @param  string|null  &$rrd_filename  output parameter for the resolved rrd filename
     * @return array
     *
     * @throws RrdGraphException
     * @throws InvalidGraph
     */
    public static function getRrdOptions(array|string $vars, ?string &$rrd_filename = null): array
    {
        // handle possible graph url input
        if (is_string($vars)) {
            $vars = Url::parseLegacyPathVars($vars);
        }

        $graph_params = new GraphParameters($vars);
        $name = $graph_params->type . '_' . $graph_params->subtype;

        /** @var GraphFactory $factory */
        $factory = app(GraphFactory::class);
        $graph = $factory->graphFor($name, $vars);

        // Run validation if rules are defined
        if ($rules = $graph->validation()) {
            $validator = \Validator::make($vars, $rules);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $vars = $validator->validated();
        }

        if ($graph instanceof \LibreNMS\Data\Graphing\AbstractGraph) {
            $graph->fill($vars);
        }

        if (! $graph->authorize()) {
            throw new RrdGraphException('No Authorization', 'No Auth', $graph_params->width, $graph_params->height);
        }

        $rrd_options = $graph->rrdDefinition($graph_params);

        if (empty($rrd_options)) {
            throw new RrdGraphException('Graph Definition Error', 'Def Error', $graph_params->width, $graph_params->height);
        }

        $rrdFiles = $graph->getRrdFiles();
        $rrd_filename = reset($rrdFiles) ?: null;

        return [...$graph_params->toRrdOptions(), ...$rrd_options];
    }

    public static function getTypes(): array
    {
        return ['device', 'port', 'application', 'munin', 'service'];
    }

    /**
     * Get an array of all graph subtypes for the given type
     *
     * @param  string  $type
     * @param  ?Device  $device
     * @return array
     */
    public static function getSubtypes(string $type, ?Device $device = null): array
    {
        $dir = base_path('includes/html/graphs/' . basename($type));
        $types = [];

        if (is_dir($dir)) {
            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.inc.php')) {
                    $name = $file->getBasename('.inc.php');
                    if ($name !== 'auth') {
                        $types[] = $name;
                    }
                }
            }
        }

        if ($device?->graphs) {
            $graphs = $device->graphs->pluck('graph');

            foreach (LibrenmsConfig::get('graph_types') as $gType => $type_data) {
                foreach (array_keys($type_data) as $subtype) {
                    if ($graphs->contains($subtype) && self::isMibGraph($gType, $subtype)) {
                        $types[] = $subtype;
                    }
                }
            }
        }

        $types = array_unique($types);
        sort($types);

        return $types;
    }

    /**
     * Check if the given graph is a mib graph
     *
     * @param  string  $type
     * @param  string  $subtype
     * @return bool
     */
    public static function isMibGraph($type, $subtype): bool
    {
        return LibrenmsConfig::get("graph_types.$type.$subtype.section") == 'mib';
    }

    public static function getOverviewGraphsForDevice(Device $device): array
    {
        if ($device->snmp_disable) {
            return Arr::wrap(LibrenmsConfig::getOsSetting('ping', 'over'));
        }

        if ($graphs = LibrenmsConfig::getOsSetting($device->os, 'over')) {
            return Arr::wrap($graphs);
        }

        $os_group = LibrenmsConfig::getOsSetting($device->os, 'group');

        return Arr::wrap(LibrenmsConfig::get("os_group.$os_group.over", LibrenmsConfig::get('os.default.over')));
    }

    /**
     * Create image to output text instead of a graph.
     *
     * @param  string  $text  Error message to display
     * @param  string|null  $short_text  Error message for smaller graph images
     * @param  int  $width  Width of graph image (defaults to 300)
     * @param  int|null  $height  Height of graph image (defaults to width / 3)
     * @param  int[]  $color  Color of text, defaults to dark red
     * @return string the generated image
     */
    public static function error(string $text, ?string $short_text, int $width = 300, ?int $height = null, array $color = [128, 0, 0]): string
    {
        $type = LibrenmsConfig::get('webui.graph_type');
        $height ??= $width / 3;

        if ($short_text !== null && $width < 200) {
            $text = $short_text;
        }

        if ($type === 'svg') {
            $rgb = implode(', ', $color);

            return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg"
xmlns:xhtml="http://www.w3.org/1999/xhtml"
viewBox="0 0 $width $height"
preserveAspectRatio="xMinYMin">
<foreignObject x="0" y="0" width="$width" height="$height" transform="translate(0,0)">
      <xhtml:div style="display:table; width:{$width}px; height:{$height}px; overflow:hidden;">
         <xhtml:div style="display:table-cell; vertical-align:middle;">
            <xhtml:div style="color:rgb($rgb); text-align:center; font-family:sans-serif; font-size:0.6em;">$text</xhtml:div>
         </xhtml:div>
      </xhtml:div>
   </foreignObject>
</svg>
SVG;
        }

        $img = imagecreate($width, $height);
        imagecolorallocatealpha($img, 255, 255, 255, 127); // transparent background

        $px = (int) ((imagesx($img) - 7.5 * strlen($text)) / 2);
        $font = $width < 200 ? 3 : 5;
        imagestring($img, $font, $px, $height / 2 - 8, $text, imagecolorallocate($img, ...$color));

        // Output the image
        ob_start();
        imagepng($img);
        $output = ob_get_clean();
        ob_end_clean();
        imagedestroy($img);

        return $output;
    }
}
