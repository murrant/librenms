<?php

namespace LibreNMS\Util;

use App\Facades\LibrenmsConfig;
use App\Graphing\Exceptions\GraphException;
use App\Graphing\GraphAccess;
use App\Graphing\GraphErrorImage;
use App\Graphing\GraphImage;
use App\Graphing\GraphQuery;
use App\Graphing\GraphRegistry;
use App\Graphing\GraphService;
use App\Models\Device;
use Illuminate\Support\Arr;
use LibreNMS\Enum\GraphOutput;
use LibreNMS\Enum\ImageFormat;

class Graph
{
    /**
     * Convenience helper to specify desired image output
     *
     * @param  array<string, mixed>|string  $vars  graph vars or a graph url
     * @param  GraphAccess|null  $access  defaults to the logged in user
     */
    public static function getImageData(array|string $vars, ?GraphAccess $access = null, ?ImageFormat $format = null, ?GraphOutput $output = null): string
    {
        $vars = is_string($vars) ? Url::parseLegacyPathVars($vars) : $vars;

        if ($format !== null) {
            $vars['graph_type'] = $format->value;
        }

        $image = self::getImage($vars, $access);

        return match ($output) {
            GraphOutput::Base64 => $image->base64(),
            GraphOutput::Inline => $image->inline(),
            default => $image->data,
        };
    }

    /**
     * Fetch a GraphImage based on the given $vars
     * Catches errors generated and always returns GraphImage
     *
     * @param  array<string, mixed>|string  $vars  graph vars or a graph url
     * @param  GraphAccess|null  $access  defaults to the logged in user
     */
    public static function getImage(array|string $vars, ?GraphAccess $access = null): GraphImage
    {
        $query = GraphQuery::fromVars($vars);

        try {
            return app(GraphService::class)->render($query, $access ?? GraphAccess::current());
        } catch (GraphException $e) {
            if (Debug::isEnabled()) {
                throw $e;
            }

            report($e); // only unexpected errors are logged

            return GraphErrorImage::forQuery($e, $query);
        }
    }

    public static function getTypes(): array
    {
        return app(GraphRegistry::class)->types();
    }

    /**
     * Get an array of all graph subtypes for the given type
     *
     * @return string[]
     */
    public static function getSubtypes(string $type, ?Device $device = null): array
    {
        return app(GraphRegistry::class)->subtypes($type, $device);
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
