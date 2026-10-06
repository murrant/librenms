<?php

namespace LibreNMS\Tests\Unit\Graphing;

use App\Graphing\GraphQuery;
use LibreNMS\Enum\ImageFormat;
use LibreNMS\Tests\TestCase;

class GraphQueryTest extends TestCase
{
    public function testParsesVars(): void
    {
        $query = GraphQuery::fromVars([
            'type' => 'multiport_bits_separate',
            'id' => '3,5,x',
            'device' => '7',
            'from' => 1700000000,
            'to' => 1700003600,
            'width' => 500,
            'height' => 200,
            'graph_type' => 'svg',
        ]);

        $this->assertSame('multiport', $query->type);
        $this->assertSame('bits_separate', $query->subtype);
        $this->assertSame('multiport_bits_separate', $query->name());
        $this->assertSame([3, 5], $query->ids);
        $this->assertSame(7, $query->deviceId);
        $this->assertSame(1700000000, $query->from);
        $this->assertSame(1700003600, $query->to);
        $this->assertSame(500, $query->width);
        $this->assertSame(200, $query->height);
        $this->assertSame(ImageFormat::Svg, $query->format);
    }

    public function testParsesLegacyPath(): void
    {
        $query = GraphQuery::fromVars('graph.php?type=port_bits&id=12&width=300');

        $this->assertSame('port', $query->type);
        $this->assertSame('bits', $query->subtype);
        $this->assertSame([12], $query->ids);
        $this->assertNull($query->deviceId);
        $this->assertSame(300, $query->width);
    }

    public function testInvalidTypeIsEmpty(): void
    {
        $query = GraphQuery::fromVars(['type' => 'nonsense']);

        $this->assertSame('', $query->type);
        $this->assertSame('', $query->subtype);
    }

    public function testParametersAreNotShared(): void
    {
        $query = GraphQuery::fromVars(['type' => 'device_bits']);

        $params = $query->parameters();
        $params->scale_min = 5;

        $this->assertNotSame($params, $query->parameters());
        $this->assertNull($query->parameters()->scale_min);
    }

    public function testWithOverrides(): void
    {
        $query = GraphQuery::fromVars(['type' => 'device_bits', 'width' => 100])->with(['width' => 900]);

        $this->assertSame(900, $query->width);
        $this->assertSame('device_bits', $query->name());
    }
}
