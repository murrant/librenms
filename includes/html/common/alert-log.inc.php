<?php

/*
* This program is free software: you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation, either version 3 of the License, or (at your
* option) any later version.  Please see LICENSE.txt at the top level of
* the source code distribution for details.
*
* @package    LibreNMS
* @subpackage graphs
* @link       https://www.librenms.org
* @copyright  2017 LibreNMS
* @author     LibreNMS Contributors
*/

$param = [];

$pagetitle[] = 'Alert Log';

$alert_states = [
    // divined from librenms/alerts.php
    'Any State' => '',
    'Ok (recovered)' => 0,
    'Alert' => 1,
    //    'Acknowledged' => 2,
    'Worse' => 3,
    'Better' => 4,
    'Changed' => 5,
];

$alert_severities = [
    // alert_rules.status is enum('ok','warning','critical')
    'Critical' => 3,
    'Warning' => 2,
    'OK' => 1,
];

$admin_verbose_details = '';
if (Gate::allows('alert.detail')) {
    $admin_verbose_details = '<th data-column-id="verbose_details" data-sortable="false">Details</th>';
}

$common_output[] = '<div class="panel panel-default panel-condensed">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-2">
                            <strong>Alert Log entries</strong>
                        </div>
                    </div>
                </div>
            ';

$device = DeviceCache::get(request()->input('device_id') ?: ($vars['device'] ?? 0));

$filterFields = \App\Models\AlertLog::filterFieldDefinitions($device->exists ? (int) $device->device_id : null);
$initialFilter = request()->array('filter');

// handle legacy url filters
if (empty($initialFilter)) {
    if (request()->filled('state')) {
        $initialFilter['state'] = ['eq' => (int) request()->input('state')];
    }
    if (request()->filled('severity')) {
        $initialFilter['rule.severity'] = ['in' => (array) request()->input('severity')];
    }
    if (request()->filled('device_group')) {
        $initialFilter['device.groups.id'] = ['eq' => (int) request()->input('device_group')];
    } elseif (request()->filled('group')) {
        $initialFilter['device.groups.id'] = ['eq' => (int) request()->input('group')];
    }
}
if ($device->exists) {
    $initialFilter['device_id'] = ['eq' => (int) $device->device_id];
}

$filterHtml = \Illuminate\Support\Facades\Blade::render(
    '<x-filter name="alertlog" :fields="$filterFields" :initial="$initial" class="tw:pb-2"/>',
    [
        'filterFields' => $filterFields,
        'initial' => $initialFilter,
    ]
);

$common_output[] = '
<template id="alertlog-filter-template">' . $filterHtml . '</template>
<div class="table-responsive">
    <table id="alertlog" class="table table-hover table-condensed table-striped" data-url="' . route('table.alertlog') . '">
        <thead>
        <tr>
            <th data-column-id="status">State</th>
            <th data-column-id="time_logged" data-order="desc" data-converter="datetime">Timestamp</th>
            <th data-column-id="details" data-sortable="false">&nbsp;</th>
            <th data-column-id="hostname">Device</th>
            <th data-column-id="alert_rule">Alert</th>
            <th data-column-id="severity">Severity</th>
            ' . $admin_verbose_details . '
        </tr>
        </thead>
    </table>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var filter = ' . json_encode($initialFilter) . ';

    var grid = $("#alertlog").bootgrid({
        ajax: true,
        rowCount: [50, 100, 250, -1],
        templates: {
            header: \'<div class="alertlog-headers-table-menu actionBar tw:block tw:sm:flex tw:justify-between tw:px-2 tw:pt-2"><p class="{{css.actions}}"></p></div><div class="row"></div>\',
            search: ""
        },
        post: function () {
            return {
                filter: filter
            };
        },
        converters: {
            datetime: {
              to: LibreNMS.Date.display
            }
        }
    }).on("loaded.rs.jquery.bootgrid", function () {
        grid.find(".incident-toggle").each(function () {
            $(this).parent().addClass(\'incident-toggle-td\');
        }).on("click", function (e) {
            var target = $(this).data("target");
            $(target).collapse(\'toggle\');
            $(this).toggleClass(\'fa-plus fa-minus\');
        });
        grid.find(".verbose-alert-details").on("click", function(e) {
            e.preventDefault();
            var alert_log_id = $(this).data(\'alert_log_id\');
            $(\'#alert_log_id\').val(alert_log_id);
            $("#alert_details_modal").modal(\'show\');
        });
        grid.find(".incident").each(function () {
            $(this).parent().addClass(\'col-lg-4 col-md-4 col-sm-4 col-xs-4\');
            if ($(this).parent().parent().find(".alert-status").hasClass(\'label-danger\')){
                $(this).parent().parent().find(".verbose-alert-details").fadeIn(0);
            }
            $(this).parent().parent().on("mouseenter", function () {
                $(this).find(".incident-toggle").fadeIn(200);
                if ($(this).find(".alert-status").hasClass(\'label-danger\')){
                    $(this).find(".command-alert-details").fadeIn(200);
                }
            }).on("mouseleave", function () {
                $(this).find(".incident-toggle").fadeOut(200);
                if ($(this).find(".alert-status").hasClass(\'label-danger\')){
                    $(this).find(".command-alert-details").fadeOut(200);
                }
            }).on("click", "td:not(.incident-toggle-td)", function () {
                var target = $(this).parent().find(".incident-toggle").data("target");
                if ($(this).parent().find(".incident-toggle").hasClass(\'fa-plus\')) {
                    $(this).parent().find(".incident-toggle").toggleClass(\'fa-plus fa-minus\');
                    $(target).collapse(\'toggle\');
                }
            });
        });
    });

    const $template = $("#alertlog-filter-template");
    if ($template.length) {
        const $content = $($template[0].content.cloneNode(true));
        $(".alertlog-headers-table-menu").prepend($content);
    }

    $(window).on("filter:apply", function (event) {
        if (event.originalEvent.detail.name === "alertlog") {
            filter = event.originalEvent.detail.filters;
            grid.bootgrid("reload");
        }
    });
});
</script>
';
