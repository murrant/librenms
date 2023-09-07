@extends('layouts.librenmsv1')

@section('title', __('settings.title'))

@section('content')

<x-device-settings-bar :device="$device" selected="ports"></x-device-settings-bar>

<h1>{{ $device->displayName() }}</h1>

<span id="message"></span>


<div class='table-responsive'>
    <table id='edit-ports' class='table table-striped'>
        <thead>
            <tr>
                <th data-column-id='ifIndex'>Index</th>
                <th data-column-id='ifName'>Name</th>
                <th data-column-id='ifAdminStatus'>Admin</th>
                <th data-column-id='ifOperStatus'>Operational</th>
                <th data-column-id='disabled' data-searchable='false' data-formatter="toggle">Disable polling</th>
                <th data-column-id='ignore' data-searchable='false' data-formatter="toggle">Ignore alert tag</th>
                <th data-column-id='ifSpeed'>ifSpeed (bits/s)</th>
                <th data-column-id='portGroup' data-sortable='false' data-searchable='false'>Port Group</th>
                <th data-column-id='port_tune' data-searchable='false' data-formatter="toggle">RRD Tune</th>
                <th data-column-id='ifAlias'>Description</th>
            </tr>
        </thead>
    </table>
</div>

<script>

//$("[name='override_config']").bootstrapSwitch('offColor','danger');
    $(document).on('blur keyup', "[name='if-alias']", function (e){
        if (e.type === 'keyup' && e.keyCode !== 13) return;
        var $this = $(this);
        var descr = $this.val();
        var port_id = $this.data('port_id');
        $.ajax({
            type: 'PUT',
            url: '<?php echo route('port.update', '?'); ?>'.replace('?', port_id),
            data: JSON.stringify({descr: descr}),
            dataType: "json",
            success: function (data) {
                $this.closest('.form-group').addClass('has-success');
                $this.next().addClass('fa-check');
                setTimeout(function(){
                    $this.closest('.form-group').removeClass('has-success');
                    $this.next().removeClass('fa-check');
                }, 2000);
              },
            error: function () {
                $(this).closest('.form-group').addClass('has-error');
                $this.next().addClass('fa-times');
                setTimeout(function(){
                   $this.closest('.form-group').removeClass('has-error');
                   $this.next().removeClass('fa-times');
                }, 2000);
            }
        });
    });
    $(document).on('blur keyup', "[name='if-speed']", function (e){
        if (e.type === 'keyup' && e.keyCode !== 13) return;
        var $this = $(this);
        var speed = $this.val().replace(/[^0-9]/gi, '') || null;
        var port_id = $this.data('port_id');
        $.ajax({
            type: 'PUT',
            url: '<?php echo route('port.update', '?'); ?>'.replace('?', port_id),
            data: JSON.stringify({speed: speed}),
            dataType: "json",
            success: function (data) {
                $this.closest('.form-group').addClass('has-success');
                $this.next().children().first().addClass('fa-check');
                $this.val(speed);
                toastr.success(data.message);
                setTimeout(function(){
                    $this.closest('.form-group').removeClass('has-success');
                    $this.next().children().first().removeClass('fa-check');
                }, 2000);
            },
            error: function () {
                $this.closest('.form-group').addClass('has-error');
                $this.next().children().first().addClass('fa-times');
                setTimeout(function(){
                   $this.closest('.form-group').removeClass('has-error');
                   $this.next().children().first().removeClass('fa-times');
                   toastr.error(data.message);
                   $this.val($this.data('ifSpeed'));
                }, 2000);
            }
        });
    });
    $(document).ready(function() {
        $('#disable-toggle').on("click", function (event) {
            // invert selection on all disable buttons
            $('input[name^="disabled_"]').trigger('click');
        });
        $('#ignore-toggle').on("click", function (event) {
            // invert selection on all ignore buttons
            $('input[name^="ignore_"]').trigger('click');
        });
        $('#disable-select').on("click", function (event) {
            // select all disable buttons
            $('.disable-check').bootstrapSwitch('state', true);
        });
        $('#ignore-select').on("click", function (event) {
            // select all ignore buttons
            $('.ignore-check').bootstrapSwitch('state', true);
        });
        $('#down-select').on("click", function (event) {
            // select ignore buttons for all ports which are down
            $('[id^="operstatus_"]').each(function () {
                var name = $(this).attr('id');
                var text = $(this).text();
                if (name && text === 'down') {
                    // get the interface number from the object name
                    var port_id = name.split('_')[1];
                    // find its corresponding checkbox and enable it
                    $('input[name="ignore_' + port_id + '"]').bootstrapSwitch('state', true);
                }
            });
        });
        $('#alerted-toggle').on("click", function (event) {
            // toggle ignore buttons for all ports which are in class red
            $('.red').each(function () {
                var name = $(this).attr('id');
                if (name) {
                    // get the interface number from the object name
                    var port_id = name.split('_')[1];
                    // find its corresponding checkbox and enable it
                    $('input[name="ignore_' + port_id + '"]').bootstrapSwitch('state', true);
                }
            });
        });
    });


</script>
@endsection

@push('style')
    <style>
        .header_actions {
            text-align: left !important;
        }
        .action_group {
            margin-right: 20px;
            white-space: nowrap;
        }
    </style>
@endpush

@push('scripts')
    <script>
        function update_port_toggle(event, state) {
            let $this = $(event.target);
            let data = {};
            data[$this.data('field')] = state;
            let port_id = $this.data('port_id');

            $.ajax({
                type: 'PATCH',
                url: '{{ route('port.update', '?') }}'.replace('?', port_id),
                data: JSON.stringify(data),
                dataType: 'json',
                success: function(data) {
                    toastr.success(data.message);
                },
                error: function(data) {
                    toastr.error(data.message);
                }
            });
        }

        let grid = $("#edit-ports").bootgrid({
            ajax: true,
            rowCount: [2, 50, 100, 250, -1],
            formatters: {
                "toggle": function (column, row) {
                    return `<input type="checkbox" class="port-toggle" data-size="small" data-port_id="${row.port_id}" data-field="${column.id}" name="${column.id}_${row.port_id}" data-off-color="danger" ${row[column.id] ? 'checked' : ''}>`
                }
            },
            templates: {
                header: '<div id="@{{ctx.id}}" class="@{{css.header}}"><div class="row">\
                            <div class="col-sm-8 actionBar header_actions">\
                            <span class="pull-left">\
                                <span class="action_group">Disable polling\
                                <div class="btn-group">\
                                <button type="button" value="Toggle" class="btn btn-default btn-sm" id="disable-toggle" title="Toggle polling for all ports">Toggle</button>\
                                <button type="button" value="Select" class="btn btn-default btn-sm" id="disable-select" title="Disable polling on all ports">Disable All</button>\
                                </div>\
                                </span>\
                                <span class="action_group tw-ml-3">Ignore alerts\
                                <div class="btn-group">\
                                <button type="button" value="Alerted" class="btn btn-default btn-sm" id="alerted-toggle" title="Toggle alerting on all currently-alerted ports">Alerted</button>\
                                <button type="button" value="Down" class="btn btn-default btn-sm" id="down-select" title="Disable alerting on all currently-down ports">Down</button>\
                                <button type="button" value="Toggle" class="btn btn-default btn-sm" id="ignore-toggle" title="Toggle alert tag for all ports">Toggle</button>\
                                <button type="button" value="Select" class="btn btn-default btn-sm" id="ignore-select" title="Disable alert tag on all ports">Ignore All</button></span>\
                                </div>\
                                </span>\
                            </span>\
                        </div>\
                        <div class="col-sm-4 actionBar"><p class="@{{css.search}}"></p><p class="@{{css.actions}}"></p></div>\
                    </div></div>'
            },
            post: function () {
                return {
                    device_id: "<?php echo $device['device_id']; ?>"
                };
            },
            url: "{{ route('table.edit-ports') }}"
        }).on("loaded.rs.jquery.bootgrid", function () {
            $(".port-toggle").bootstrapSwitch().on('switchChange.bootstrapSwitch', update_port_toggle);

            init_select2('.port_group_select', 'port-group', {}, null, 'No Group');
            var last_port_group_change;
            $('.port_group_select').on('change', function (e) {
                let $target = $(e.target);
                let port_id = $target.data('port_id');
                let groups = JSON.stringify({"groups": $target.val()});

                // don't send the same update multiple times... silly select2
                if (last_port_group_change === (port_id + groups)) {
                    return;
                }

                last_port_group_change = port_id + groups;

                $.ajax({
                    type: "PUT",
                    url: '<?php echo route('port.update', '?'); ?>'.replace('?', port_id),
                    data: groups,
                    success: function (data) {
                        toastr.success(data.message)
                    },
                    error: function (data) {
                        toastr.error(data.responseJSON.message)
                    }
                });
            });
        });
    </script>
@endpush
