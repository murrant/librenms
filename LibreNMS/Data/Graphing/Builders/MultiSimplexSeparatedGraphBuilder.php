<?php

/**
 * MultiSimplexSeparatedGraphBuilder.php
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
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Graphing\Builders;

use App\Facades\LibrenmsConfig;
use LibreNMS\Data\Graphing\GraphParameters;
use LibreNMS\Data\Store\Rrd;

class MultiSimplexSeparatedGraphBuilder
{
    private string $unitText = '';
    private string $totalUnits = '';
    private string $colours = 'mixed';
    private ?float $scaleMin = null;
    private ?float $scaleMax = null;
    private ?float $divider = null;
    private ?float $multiplier = null;
    private bool $textOrig = false;
    private bool $nototal = false;
    private int $descrLen = 12;

    private array $datasets = [];

    public function unitText(string $unitText): self
    {
        $this->unitText = $unitText;

        return $this;
    }

    public function totalUnits(string $totalUnits): self
    {
        $this->totalUnits = $totalUnits;

        return $this;
    }

    public function colours(string $colours): self
    {
        $this->colours = $colours;

        return $this;
    }

    public function scaleMin(float $scaleMin): self
    {
        $this->scaleMin = $scaleMin;

        return $this;
    }

    public function scaleMax(float $scaleMax): self
    {
        $this->scaleMax = $scaleMax;

        return $this;
    }

    public function divider(float $divider): self
    {
        $this->divider = $divider;

        return $this;
    }

    public function multiplier(float $multiplier): self
    {
        $this->multiplier = $multiplier;

        return $this;
    }

    public function textOrig(bool $textOrig = true): self
    {
        $this->textOrig = $textOrig;

        return $this;
    }

    public function noTotal(bool $noTotal = true): self
    {
        $this->nototal = $noTotal;

        return $this;
    }

    public function descrLen(int $descrLen): self
    {
        $this->descrLen = $descrLen;

        return $this;
    }

    public function addDataset(
        string $filename,
        string $ds,
        string $description,
        ?string $colour = null
    ): self {
        $this->datasets[] = [
            'filename' => $filename,
            'ds' => $ds,
            'descr' => $description,
            'colour' => $colour,
        ];

        return $this;
    }

    public function build(GraphParameters $graph_params): array
    {
        $float_precision = $graph_params->float_precision;

        if ($this->scaleMin !== null) {
            $graph_params->scale_min = (int) $this->scaleMin;
        }
        if ($this->scaleMax !== null) {
            $graph_params->scale_max = (int) $this->scaleMax;
        }

        $previous = $graph_params->visible('previous');
        $prev_from = $graph_params->prev_from;
        $from = $graph_params->from;
        $period = $graph_params->period;

        $descr_len = $this->descrLen;
        if ($this->nototal) {
            $descr_len += 2;
        }

        $unitlen = 10;
        if ($this->nototal) {
            $unitlen += 2;
        }

        $unit_text = Rrd::fixedSafeDescr($this->unitText, $unitlen);

        $rrd_options = [];
        $rrd_options[] = 'COMMENT:' . Rrd::fixedSafeDescr($this->unitText, $descr_len) . "        Now      Min     Max     Avg\l";

        $seperatorX = '';
        $thingX = '';
        $plusX = '';
        $plusesX = '';
        $stack = '';

        $colour_iter = 0;
        foreach ($this->datasets as $i => $rrd) {
            $colour = $rrd['colour'];
            if ($colour === null) {
                if (! LibrenmsConfig::get("graph_colours.{$this->colours}.{$colour_iter}")) {
                    $colour_iter = 0;
                }
                $colour = LibrenmsConfig::get("graph_colours.{$this->colours}.{$colour_iter}");
                $colour_iter++;
            }

            $descr = Rrd::fixedSafeDescr($rrd['descr'], $descr_len);
            $ds = $rrd['ds'];
            $filename = $rrd['filename'];

            $rrd_options[] = 'DEF:' . $ds . $i . '=' . $filename . ':' . $ds . ':AVERAGE';
            $rrd_options[] = 'DEF:' . $ds . $i . 'min=' . $filename . ':' . $ds . ':MIN';
            $rrd_options[] = 'DEF:' . $ds . $i . 'max=' . $filename . ':' . $ds . ':MAX';

            if ($previous) {
                $rrd_options[] = 'DEF:' . $i . 'X=' . $filename . ':' . $ds . ':AVERAGE:start=' . $prev_from . ':end=' . $from;
                $rrd_options[] = 'SHIFT:' . $i . "X:$period";
                $thingX .= $seperatorX . $i . 'X,UN,0,' . $i . 'X,IF';
                $plusesX .= $plusX;
                $seperatorX = ',';
                $plusX = ',+';
            }

            if (! $this->nototal) {
                $rrd_options[] = 'VDEF:tot' . $ds . $i . '=' . $ds . $i . ',TOTAL';
            }

            if ($i > 0) {
                $stack = ':STACK';
            }

            $g_defname = $ds;
            if ($this->multiplier !== null) {
                $g_defname = $ds . '_cdef';
                $rrd_options[] = 'CDEF:' . $g_defname . $i . '=' . $ds . $i . ',' . $this->multiplier . ',*';
                $rrd_options[] = 'CDEF:' . $g_defname . $i . 'min=' . $ds . $i . 'min,' . $this->multiplier . ',*';
                $rrd_options[] = 'CDEF:' . $g_defname . $i . 'max=' . $ds . $i . 'max,' . $this->multiplier . ',*';
            } elseif ($this->divider !== null) {
                $g_defname = $ds . '_cdef';
                $rrd_options[] = 'CDEF:' . $g_defname . $i . '=' . $ds . $i . ',' . $this->divider . ',/';
                $rrd_options[] = 'CDEF:' . $g_defname . $i . 'min=' . $ds . $i . 'min,' . $this->divider . ',/';
                $rrd_options[] = 'CDEF:' . $g_defname . $i . 'max=' . $ds . $i . 'max,' . $this->divider . ',/';
            }

            if ($this->textOrig) {
                $t_defname = $ds;
            } else {
                $t_defname = $g_defname;
            }

            $rrd_options[] = 'AREA:' . $g_defname . $i . '#' . $colour . ':' . $descr . "$stack";

            $rrd_options[] = 'GPRINT:' . $t_defname . $i . ':LAST:%5.' . $float_precision . 'lf%s';
            $rrd_options[] = 'GPRINT:' . $t_defname . $i . 'min:MIN:%5.' . $float_precision . 'lf%s';
            $rrd_options[] = 'GPRINT:' . $t_defname . $i . 'max:MAX:%5.' . $float_precision . 'lf%s';
            $rrd_options[] = 'GPRINT:' . $t_defname . $i . ':AVERAGE:%5.' . $float_precision . 'lf%s\\n';

            if (! $this->nototal) {
                $rrd_options[] = 'GPRINT:tot' . $ds . $i . ':%6.' . $float_precision . 'lf%s' . Rrd::safeDescr($this->totalUnits);
            }

            $rrd_options[] = 'COMMENT:\\n';
        }

        if ($previous) {
            if ($this->multiplier !== null) {
                $rrd_options[] = 'CDEF:X=' . $thingX . $plusesX . ',' . $this->multiplier . ',*';
            } elseif ($this->divider !== null) {
                $rrd_options[] = 'CDEF:X=' . $thingX . $plusesX . ',' . $this->divider . ',/';
            } else {
                $rrd_options[] = 'CDEF:X=' . $thingX . $plusesX;
            }

            $rrd_options[] = 'AREA:X#99999999:';
            $rrd_options[] = 'LINE1.25:X#666666:';
        }

        return $rrd_options;
    }
}
