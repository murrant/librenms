<?php
/**
 * print-alert-rules.inc.php
 *
 * LibreNMS print alert rules table
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
 * @copyright  2020 The LibreNMS Community
 * @author     Original Author <unknown>
 * @author     Joseph Tingiris <joseph.tingiris@gmail.com>
 */
if (! Auth::user()->hasGlobalAdmin()) {
    exit('ERROR: You need to be admin');
}

use App\Models\AlertRule;
use LibreNMS\Alerting\QueryBuilderParser;

$no_refresh = true;

?>
<div class="row">
    <div class="col-sm-12">
        <span id="message"></span>
    </div>
</div>
<?php
if (isset($_POST['create-default'])) {
    $default_rules = array_filter(get_rules_from_json(), fn ($rule) => isset($rule['default']) && $rule['default']);

    $default_extra = [
        'mute' => false,
        'count' => -1,
        'delay' => 300,
        'invert' => false,
        'interval' => 300,
    ];

    foreach ($default_rules as $add_rule) {
        $extra = $default_extra;
        if (isset($add_rule['extra'])) {
            $extra = array_replace($extra, json_decode($add_rule['extra'], true));
        }

        $qb = QueryBuilderParser::fromJson($add_rule['builder']);

        $insert = [
            'builder' => json_encode($add_rule['builder']),
            'query' => $qb->toSql(),
            'severity' => 'critical',
            'extra' => json_encode($extra),
            'disabled' => 0,
            'name' => $add_rule['name'],
        ];

        dbInsert($insert, 'alert_rules');
    }
    unset($qb);
}

require_once 'includes/html/modal/new_alert_rule.inc.php';
require_once 'includes/html/modal/delete_alert_rule.inc.php'; // Also dies if !Auth::user()->hasGlobalAdmin()
require_once 'includes/html/modal/alert_rule_collection.inc.php'; // Also dies if !Auth::user()->hasGlobalAdmin()
require_once 'includes/html/modal/alert_rule_list.inc.php'; // Also dies if !Auth::user()->hasGlobalAdmin()

require_once 'includes/html/modal/edit_transport_group.inc.php';
require_once 'includes/html/modal/edit_alert_transport.inc.php';

?>
<div class="table-responsive">
<table id="alert-rules-table" class="table table-condensed table-hover table-striped">
<thead>
    <tr>
        <th data-column-id="type" data-formatter="type">Type<th>
        <th data-column-id="name" data-formatter="name">Name</th>
        <th data-column-id="devices" data-formatter="devices">Devices<th>
        <th data-column-id="transports" data-formatter="transports">Transports<th>
        <th data-column-id="extra" data-formatter="extra">Notification Settings</th>
        <th data-column-id="rule" data-formatter="rule">Rule</th>
        <th data-column-id="severity" data-formatter="severity">Severity</th>
        <th data-column-id="status" data-formatter="status">Status</th>
        <th data-column-id="disabled" data-formatter="toggle" data-width="55px">Enabled</th>
        <th data-column-id="actions" data-formatter="actions">Action</th>
    </tr>
</thead>
</table>
</div>
<?php

if (! AlertRule::exists()) {
    echo '<div class="row">
        <div class="col-sm-12">
        <form role="form" method="post">
        ' . csrf_field() . '
        <p class="text-center">
        <button type="submit" class="btn btn-success btn-lg" id="create-default" name="create-default"><i class="fa fa-plus"></i> Click here to create the default alert rules!</button>
        </p>
        </form>
        </div>
        </div>';
}
?>
<script>
function describeAlertRule(rule, ignore_disabled = false) {
    if (rule.disabled && ! ignore_disabled) {
        return {
            icon: 'fa-pause',
            icon_color: 'text-default',
            background_color: 'active',
            message: ` ${rule.name} is OFF`
        }
    }

    if (rule.state === 0) {
        return {
            icon: 'fa-check',
            icon_color: 'text-success',
            background_color: '',
            message: `All devices matching ${rule.name} are OK`
        }
    }

    let message = '';
    // active or acknowledged
    if (rule.state === 1 || rule.state === 2) {
        message = `Some devices matching ${rule.name} are currently alerting`;
    }

    switch (rule.severity) {
        case 'critical':
            return {
                icon: 'fa-exclamation',
                icon_color: 'text-danger',
                background_color: 'danger',
                message: message
            };
        case 'warning':
            return {
                icon: 'fa-warning',
                icon_color: 'text-warning',
                background_color: 'warning',
                message: message
            };
        case 'ok':
            return {
                icon: 'fa-check',
                icon_color: 'text-success',
                background_color: 'success',
                message: message
            };
        default:
            return {
                icon: 'fa-info',
                icon_color: 'text-info',
                background_color: 'info',
                message: message
            };
    }
}

function addPopover(element, title = null, message = null, placement = 'right') {
    element.dataset.toggle = 'popover';
    element.dataset.container = 'body';
    element.dataset.placement = placement;
    if (title) {
        element.title = title;
    }
    if (message) {
        element.dataset.content = message;
    }
}

$('#alert-rules-table').bootgrid({
    rowCount: [50, 100, 250, -1],
    ajax: true,
    url: '<?php echo route('table.alert-rule'); ?>',
    post() {
        return {
            device: <?php echo json_encode($device['device_id'] ?? ''); ?>
        };
    },
    templates: {
        header: '<div id="{{ctx.id}}" class="{{css.header}}"><div class="row"> \
                <div class="col-sm-8 actionBar tw:flex tw:justify-start tw:items-center"> \
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#create-alert" data-device_id="<?php echo $device['device_id'] ?? '' ?>">Create new alert rule</button> \
                <i class="tw:px-4"> - OR - </i> \
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#search_rule_modal" data-device_id="<?php echo $device['device_id'] ?? '' ?>">Create rule from collection</button> \
                </div> \
                <div class="col-sm-4 actionBar"><p class="{{css.search}}"></p><p class="{{css.actions}}"></p></div></div></div>'
    },
    formatters: {
        type(column, row) {
            let types = 'Global';
            let icon = 'fa-globe';
            let color = 'text-success';

            if (row.devices.length > 0) {
                color = 'text-primary';
                types = row.devices.map(device => device.type)
                    .filter((value, index, self) => self.indexOf(value) === index)
                    .map(type => type.charAt(0).toUpperCase() + type.slice(1))
                    .join(', ');

                if (types === 'Group') {
                    icon = 'fa-th';
                } else if (types === 'Location') {
                    icon = 'fa-map-marker';
                } else if (types === 'Device') {
                    icon = 'fa-server';
                } else {
                    icon = 'fa-cubes';
                }
            }

            let el = document.createElement('i');
            addPopover(el, `${types} Alert Rule #${row.id}`, row.name);
            el.classList.add('fa', icon, 'fa-lg', 'fa-fw', color);

            return el.outerHTML;
        },
        name(column, row) {
            let link = document.createElement('a');
            link.href = '/alerts/rule_id=' + row.id;
            link.textContent = row.name;
            addPopover(link, null, 'View Alerts for this rule');

            return link.outerHTML;
        },
        devices(column, row) {
            // no mapped devices
            if (row.devices.length === 0) {
                if (row.invert_map) {
                    let span = document.createElement('span');
                    span.textContent = 'No Devices';
                    span.className = 'text-danger';
                    addPopover(span, null, 'No Devices included in this rule');
                    return span.outerHTML;
                }

                let link = document.createElement('a');
                link.href = '<?php echo url('devices'); ?>';
                link.target = '_blank';
                link.textContent = 'All Devices';
                addPopover(link, null, 'View All Devices');

                return link.outerHTML;
            }

            let div = document.createElement('div');


            for (const device of row.devices) {
                if (row.invert_map) {
                    const strong = document.createElement('strong');
                    const em = document.createElement('em');
                    em.textContent = 'EXCEPT';
                    strong.appendChild(em);
                    div.appendChild(strong);
                    div.append(' ');
                }

                let link = document.createElement('a');
                link.href = device.url;
                link.textContent = device.name;
                if (device.type === 'device') {
                    addPopover(link, row.invert_map ? 'All devices EXCEPT this device.' : 'Only this device.', device.name);
                } else if (device.type === 'group') {
                    addPopover(link, device.invert_map ? 'All devices EXCEPT this group.' : 'Only devices in this group.', device.name);
                } else if (device.type === 'location') {
                    addPopover(link, device.invert_map ? 'All locations EXCEPT this location.' : 'Only devices in this location.', device.name);
                }

                div.appendChild(link);
                div.appendChild(document.createElement('br'));
            }

            return div.innerHTML;
        },
        transports(column, row) {
            let div = document.createElement('div');

            for (const transport of row.transports) {
                let link = document.createElement('a');
                link.href = '';
                link.textContent = transport.name;

                if (transport.type === 'group') {
                    addPopover(link, 'Edit transport group', transport.name);
                    link.dataset.group_id = transport.id;
                    link.dataset.target = '#edit-transport-group';
                } else if (transport.type == 'single') {
                    addPopover(link, 'Edit transport', transport.name);
                    link.dataset.transport_id = transport.id;
                    link.dataset.target = '#edit-alert-transport';
                } else if (transport.type == 'default') {
                    addPopover(link, 'Edit default transport', transport.name);
                    link.dataset.transport_id = transport.id;
                    link.dataset.target = '#edit-alert-transport';
                }

                link.dataset.toggle = 'modal';
                div.appendChild(link);

                div.appendChild(document.createElement('br'));
            }

            return div.innerHTML;
        },
        rule(column, row) {
            let element = document.createElement('span');
            element.textContent = row[column.id];

            return element.innerHTML;
        },
        status(column, row) {
            const description = describeAlertRule(row);

            const div = document.createElement('div');
            const a = document.createElement('a');
            a.href = `/alerts/rule_id=${row.id}`;

            const span = document.createElement('span');
            addPopover(span, null, description.message, 'top');
            span.id = `alert-rule-${row.id}`;
            span.className = `fa fa-fw fa-2x ${description.icon} ${description.icon_color}`;

            a.appendChild(span);
            div.appendChild(a);

            if (row.extra?.mute) {
                const mute_div = document.createElement('div');
                addPopover(mute_div, null, `Alerts for ${row.name ?? ''} are muted`);
                mute_div.className = 'fa fa-fw fa-2x fa-volume-off text-primary';
                div.appendChild(mute_div);
            }

            // acknowledged
            if (row.state === 2) {
                const ack_div = document.createElement('div');
                addPopover(ack_div, null, `Some Alerts for ${row.name ?? ''} are acknowledged`);
                ack_div.className = 'fa fa-fw fa-2x fa-sticky-note text-info';
                div.appendChild(ack_div);
            }

            return div.outerHTML;
        },
        toggle(column, row) {
            const description = describeAlertRule(row, true);

            const div = document.createElement('div');
            div.id = `on-off-checkbox-${row.id}`;
            div.className = 'btn-group btn-group-sm';
            div.setAttribute('role', 'group');
            addPopover(div, null, row.name + ' is ' + (row.disabled ? 'OFF' : 'ON'), 'top');

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'alert-rule';
            input.id = row.id;
            if (! row.disabled) {
                input.setAttribute('checked', '');
            }

            input.dataset.orig_class = description.background_color;
            input.dataset.orig_colour = description.icon_color;
            input.dataset.orig_state = description.icon;
            input.dataset.alert_id = row.id;
            input.dataset.alert_name = row.name;
            input.dataset.alert_status = description.message;
            input.dataset.size = 'small';

            div.appendChild(input);

            return div.outerHTML;
        },
        extra(column, row) {
            const count = row.extra?.count ?? '';
            const delay = row.extra?.delay ?? '';
            const interval = row.extra?.interval ?? '';

            return '<small>Max: ' + count + '<br />Delay: ' + delay + '<br />Interval: ' + interval + '</small>';
        },
        severity(column, row) {
            return row.severity.charAt(0).toUpperCase() + row.severity.slice(1);
        },
        actions(column, row) {
            const btnGroup = document.createElement('div');
            btnGroup.className = 'btn-group btn-group-sm tw:flex tw:flex-nowrap';
            btnGroup.setAttribute('role', 'group');

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-primary';
            editBtn.setAttribute('aria-label', 'Edit');
            addPopover(editBtn, 'Edit alert rule', row.name, 'left');
            editBtn.dataset.toggle = 'modal';
            editBtn.dataset.target = '#create-alert';
            editBtn.dataset.rule_id = row.id;
            editBtn.name = 'edit-alert-rule';
            editBtn.innerHTML = '<i class="fa fa-lg fa-pencil" aria-hidden="true"></i>';

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger';
            deleteBtn.setAttribute('aria-label', 'Delete');
            addPopover(deleteBtn, 'Delete alert rule', row.name, 'left');
            deleteBtn.dataset.toggle = 'modal';
            deleteBtn.dataset.target = '#confirm-delete';
            deleteBtn.dataset.alert_id = row.id;
            deleteBtn.dataset.alert_name = row.name;
            deleteBtn.name = 'delete-alert-rule';
            deleteBtn.title = 'Delete alert rule';
            deleteBtn.innerHTML = '<i class="fa fa-lg fa-trash" aria-hidden="true"></i>';

            btnGroup.appendChild(editBtn);
            btnGroup.appendChild(deleteBtn);

            return btnGroup.outerHTML;
        }
    }
}).on("loaded.rs.jquery.bootgrid", function (e) {
    $("[data-toggle='modal'], [data-toggle='popover']").popover({
        trigger: 'hover'
    });

    // fix modal click event listener not triggering
    $("[data-toggle='modal']").click(function(e) {
        e.preventDefault();
        let modal = $(this).data("target");
        $(modal).modal('show', this);
     });


    $("[name='alert-rule']").bootstrapSwitch('offColor','danger');
    $('input[name="alert-rule"]').on('switchChange.bootstrapSwitch',  function(event, state) {
        event.preventDefault();
        var $this = $(this);
        var alert_id = $(this).data("alert_id");
        var alert_name = $(this).data("alert_name");
        var alert_status = $(this).data("alert_status");
        var orig_state = $(this).data("orig_state");
        var orig_colour = $(this).data("orig_colour");
        var orig_class = $(this).data("orig_class");
        $.ajax({
            type: 'PUT',
            url: '<?php echo route('alert-rule.toggle', ':rule_id'); ?>'.replace(':rule_id', alert_id),
            data: {state: state},
            dataType: "json",
            success: function (msg) {
                if (msg.status === 200) {
                    if (state) {
                        $('#alert-rule-' + alert_id).removeClass('fa-pause');
                        $('#alert-rule-' + alert_id).addClass(orig_state);
                        $('#alert-rule-' + alert_id).removeClass('text-default');
                        $('#alert-rule-' + alert_id).addClass(orig_colour);
                        $('#alert-rule-' + alert_id).attr('data-content', alert_status);
                        $('#on-off-checkbox-' + alert_id).attr('data-content', alert_name + ' is ON');
                        $('#rule_id_' + alert_id).removeClass('active');
                        $('#rule_id_' + alert_id).addClass(orig_class);
                    } else {
                        $('#alert-rule-' + alert_id).removeClass(orig_state);
                        $('#alert-rule-' + alert_id).addClass('fa-pause');
                        $('#alert-rule-' + alert_id).removeClass(orig_colour);
                        $('#alert-rule-' + alert_id).addClass('text-default');
                        $('#alert-rule-' + alert_id).attr('data-content', alert_name + ' is OFF');
                        $('#on-off-checkbox-' + alert_id).attr('data-content', alert_name + ' is OFF');
                        $('#rule_id_' + alert_id).removeClass('warning');
                        $('#rule_id_' + alert_id).addClass('active');
                    }
                } else {
                    $("#message").html('<div class="alert alert-info">This alert could not be updated.</div>');
                    $('#' + alert_id).bootstrapSwitch('toggleState', true);
                }
            },
            error: function () {
                $("#message").html('<div class="alert alert-info">This alert could not be updated.</div>');
                $('#' + alert_id).bootstrapSwitch('toggleState', true);
            }
        });
    });
});
</script>
