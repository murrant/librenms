@extends('layouts.librenmsv1')

@section('content')

@include('alert.templates.modals.edit')
@include('alert.templates.modals.delete')

<div class="container">
    <x-panel>
        <x-slot:title>{{ __('Alert Templates') }}</x-slot:title>

        <x-slot:slot class="tw:p-0!">
        <div class="table-responsive">
            <table id="templatetable" class="table table-hover table-condensed" width="100%">
                <thead>
                <tr>
                    <th data-column-id="id" data-searchable="false" data-identifier="true" data-type="numeric">#</th>
                    <th data-column-id="templatename" data-order="asc">Name</th>
                    <th data-column-id="alert_rules" data-searchable="false" data-formatter="alert_rules">Alert Rules</th>
                    <th data-column-id="actions" data-searchable="false" data-formatter="commands">Action</th>
                    <th data-column-id="old_template" data-searchable="false" data-visible="false">Old template</th>
                </tr>
                </thead>
                <tbody>
                @foreach($templates as $template)
                <tr data-row-id="{{ $template->id }}">
                    <td>{{ $template->id }}</td>
                    <td>{{ $template->name }}</td>
                    <td>{{ json_encode($template->alert_rules) }}</td>
                    <td>{{ str_contains((string) $template['template'], '{/if}') ? '1' : '' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        </x-slot:slot>
    </x-panel>
</div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var grid = $('#templatetable').bootgrid({
                    rowCount: [50, 100, 250, -1],
                    templates: {
                        header: '<div id="@{{ctx.id}}" class="bootgrid-header tw:px-4 tw:block tw:sm:flex tw:justify-between"> \
                @can('create', AlertTemplate::class)
                    <div> \
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#alert-template" data-template_id="">Create new alert template</button> \
                    </div> \
                @else
                    <div></div> \
                @endcan
                    <div><p class="@{{css.search}}"></p><p class="@{{css.actions}}"></p></div></div>'
                },
                formatters: {
                "commands": function(column, row) {
                    var response = '';
                    //FIXME remove Deprecated template
                    if (row.old_template == "1") {
                        response = "<button type='button' class='btn btn-xs btn-warning' data-content=' class='btn btn-xs btn-warning' data-content='><i class='fa fa-exclamation-triangle' title='This is a legacy template and needs converting, please edit this template and click convert then save'><i class='fa fa-exclamation-triangle'></i></button> ";
                    }
                    if(row.id == 0) {
                        response = response + "<button type=\"button\" class=\"btn btn-xs btn-primary command-edit\" data-toggle='modal' data-target='#alert-template' data-template_id=\"" + row.id + "\" data-template_action='edit' name='edit-alert-template'><i class=\"fa fa-pencil\" aria-hidden=\"true\"></i></button> " + "<button type=\"button\" class=\"btn btn-xs btn-danger command-delete\" disabled=\"disabled\"><i class=\"fa fa-trash-o\" aria-hidden=\"true\"></i></button>";
                    } else {
                        response = response + "<button type=\"button\" class=\"btn btn-xs btn-primary command-edit\" data-toggle='modal' data-target='#alert-template' data-template_id=\"" + row.id + "\" data-template_action='edit' name='edit-alert-template'><i class=\"fa fa-pencil\" aria-hidden=\"true\"></i></button> " + "<button type=\"button\" class=\"btn btn-xs btn-danger command-delete\" data-toggle=\"modal\" data-target='#confirm-delete-alert-template' data-template_id=\"" + row.id + "\" name='delete-alert-template'><i class=\"fa fa-trash-o\" aria-hidden=\"true\"></i></button>";
                    }
                    return response;
                },
                "alert_rules": function(column, row) {
                    var container = $('<div>');
                    console.log(typeof row.alert_rules, row.alert_rules);
                    var alert_rules = JSON.parse(row.alert_rules);

                    alert_rules.forEach(function(rule) {
                        container.append(document.createTextNode(rule.name));
                        container.append('<br>');
                    });

                    return container.html();
                },
            },
        }).on("loaded.rs.jquery.bootgrid", function() {
                /* Executes after data is loaded and rendered */
                grid.find(".command-edit").on("click", function(e) {
                    var localtmpl_id = $(this).data("template_id");
                    if(localtmpl_id == 0) {
                        $('#default_template').val("1");
                        $('#template_id').val({{ Js::from($default_template_id) }});
                    } else {
                        $('#default_template').val("0");
                        $('#template_id').val(localtmpl_id);
                    }
                    $("#alert-template").modal('show');
                }).end().find(".command-delete").on("click", function(e) {
                    $('#template_id').val($(this).data("template_id"));
                    $('#confirm-delete-alert-template').modal('show');
                });
            });
        });
    </script>
@endpush
