<?php

namespace App\Graphing;

use App\Graphing\Exceptions\GraphException;
use LibreNMS\Enum\ImageFormat;
use LibreNMS\Util\Graph;

/**
 * The single place graph errors are turned into images
 */
final class GraphErrorImage
{
    public static function forQuery(GraphException $e, GraphQuery $query): GraphImage
    {
        return self::make($e, $query->format, $query->width, $query->height);
    }

    /**
     * For errors that happen before input could be parsed, only trusts numeric dimensions.
     *
     * @param  array<string, mixed>  $vars
     */
    public static function forVars(GraphException $e, array $vars): GraphImage
    {
        $width = isset($vars['width']) && is_numeric($vars['width']) ? (int) $vars['width'] : 0;
        $height = isset($vars['height']) && is_numeric($vars['height']) ? (int) $vars['height'] : 0;
        $format = ImageFormat::forGraph(is_string($vars['graph_type'] ?? null) ? $vars['graph_type'] : null);

        return self::make($e, $format, $width, $height);
    }

    public static function make(GraphException $e, ImageFormat $format, int $width = 0, int $height = 0): GraphImage
    {
        $data = Graph::error(
            $e->getMessage(),
            $e->shortText(),
            $width > 0 ? min($width, 10000) : 300,
            $height > 0 ? min($height, 10000) : null,
        );

        return new GraphImage($format, 'Error', $data);
    }
}
