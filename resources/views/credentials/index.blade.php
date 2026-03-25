@extends('layouts.librenmsv1')

@section('title', __('Credentials'))

@section('content')
<div class="container-fluid" x-data="credentialManager()">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading tw:flex tw:justify-between">
                    <div class="panel-title"><i class="fa fa-key" aria-hidden="true"></i> {{ __('Credential Management') }}</div>
                    @can('credential.create')
                    <button class="btn btn-success btn-sm" @click="openCreateModal()">
                        <i class="fa fa-plus"></i> {{ __('Add Credential') }}
                    </button>
                    @endcan
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-condensed table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Default for Discovery') }}</th>
                                    <th>{{ __('Devices') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($credentials as $credential)
                                <tr>
                                    <td>{{ $credential->name }}</td>
                                    <td>
                                        <span class="label label-info">
                                            {{ (new $credential->type)->name() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($credential->is_default)
                                            <span class="label label-success">
                                                <i class="fa fa-check"></i> {{ __('Yes') }}
                                            </span>
                                        @else
                                            <span class="label label-default">
                                                {{ __('No') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-default btn-xs" @click="showDevices({{ $credential->id }}, '{{ addslashes($credential->name) }}')">
                                            <span class="badge">{{ $credential->devices_count }}</span>
                                        </button>
                                    </td>
                                    <td class="tw:text-nowrap">
                                        @can('credential.update')
                                        <button class="btn btn-primary btn-xs edit-credential-btn"
                                                data-credential='@json($credential)'
                                                data-type-name="{{ (new $credential->type)->name() }}"
                                                @click="openEditModal($el.dataset.credential, $el.dataset.typeName)">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        @endcan
                                        @can('credential.delete')
                                        <form action="{{ route('credentials.destroy', $credential->id) }}" method="POST" class="tw:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs"
                                                    onclick="return confirm('{{ __('Are you sure you want to delete this credential?') }}')">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals for Create/Edit --}}
    <div class="modal fade" id="credentialModal" tabindex="-1" role="dialog" x-ref="modal">
        <div class="modal-dialog" role="document">
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" x-text="isEdit ? '{{ __('Edit Credential') }}' : '{{ __('Add Credential') }}'"></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" required x-model="formData.name">
                        </div>
                        <div class="form-group">
                            <label>{{ __('Type') }}</label>
                            <template x-if="!isEdit">
                                <select name="type" class="form-control" required x-model="formData.type" @change="fetchSchema()">
                                    <option value="">{{ __('Select Type') }}</option>
                                    @foreach($types as $class => $name)
                                        <option value="{{ $class }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </template>
                            <template x-if="isEdit">
                                <input type="text" class="form-control" readonly disabled :value="formData.typeName">
                            </template>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_default" value="1" x-model="formData.is_default">
                                {{ __('Default for Discovery') }}
                            </label>
                        </div>

                        <div id="credential_fields">
                            <template x-for="fieldKey in Object.keys(schema)" :key="fieldKey">
                                <div class="form-group" x-data="{ field: schema[fieldKey], key: fieldKey }">
                                    <label x-text="field.label || key"></label>
                                    <template x-if="field.type === 'enum'">
                                        <select class="form-control" x-model="formData.data[key]" :required="field.required">
                                            <template x-for="opt in (field.options || [])">
                                                <option :value="typeof opt === 'string' ? opt : opt.value"
                                                        x-text="typeof opt === 'string' ? opt : opt.text">
                                                </option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="field.type !== 'enum'">
                                        <div class="input-group">
                                            <input class="form-control"
                                                   :type="field.secret && !field.unmasked ? 'password' : (field.type === 'number' ? 'number' : 'text')"
                                                   x-model="formData.data[key]"
                                                   :required="field.required && (!field.secret || !isEdit)"
                                                   :placeholder="field.secret && isEdit && !field.unmasked ? '********' : ''">
                                            <template x-if="field.secret && isEdit">
                                                <span class="input-group-btn">
                                                    <button type="button" class="btn btn-default" @click="unmaskField(key)" title="{{ __('Unmask') }}">
                                                        <i class="fa" :class="field.unmasked ? 'fa-eye-slash' : 'fa-eye'"></i>
                                                    </button>
                                                </span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <input type="hidden" name="data" :value="JSON.stringify(prepareDataForSubmit())">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary" x-text="isEdit ? '{{ __('Update') }}' : '{{ __('Save') }}'"></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal for showing devices --}}
    <div class="modal fade" id="devicesModal" tabindex="-1" role="dialog" x-ref="devicesModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">{{ __('Devices using') }} <span x-text="selectedCredentialName"></span></h4>
                </div>
                <div class="modal-body">
                    <div x-show="loadingDevices" class="tw:text-center">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                    <div x-show="!loadingDevices">
                        <template x-if="devices.length === 0">
                            <p class="tw:text-center">{{ __('No devices are using this credential.') }}</p>
                        </template>
                        <template x-if="devices.length > 0">
                            <table class="table table-hover table-condensed table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('Hostname') }}</th>
                                        <th>{{ __('SysName') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="device in devices" :key="device.device_id">
                                        <tr>
                                            <td>
                                                <a :href="device.url" x-text="device.hostname"></a>
                                            </td>
                                            <td x-text="device.sysName || device.display || ''"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function credentialManager() {
        return {
            isEdit: false,
            formAction: '{{ route('credentials.store') }}',
            schema: {},
            loadingDevices: false,
            devices: [],
            selectedCredentialName: '',
            formData: {
                id: null,
                name: '',
                type: '',
                typeName: '',
                is_default: false,
                data: {}
            },
            openCreateModal() {
                this.isEdit = false;
                this.formAction = '{{ route('credentials.store') }}';
                this.formData = { id: null, name: '', type: '', typeName: '', is_default: false, data: {} };
                this.schema = {};
                $(this.$refs.modal).modal('show');
            },
            openEditModal(credentialJson, typeName) {
                const credential = JSON.parse(credentialJson);
                this.isEdit = true;
                this.formAction = '/credentials/' + credential.id;
                this.formData = {
                    id: credential.id,
                    name: credential.name,
                    type: credential.type,
                    typeName: typeName,
                    is_default: !!credential.is_default,
                    data: Object.assign({}, credential.data)
                };
                // Clear password fields in formData so they show as empty/placeholder
                this.fetchSchema().then(() => {
                    Object.entries(this.schema).forEach(([key, field]) => {
                        if (field.secret) {
                            this.formData.data[key] = '';
                        }
                    });
                });
                $(this.$refs.modal).modal('show');
            },
            async unmaskField(key) {
                const field = this.schema[key];
                if (field.unmasked) {
                    field.unmasked = false;
                    this.formData.data[key] = '';
                    return;
                }

                try {
                    const response = await fetch(`/credentials/${this.formData.id}/unmask/${key}`);
                    if (response.ok) {
                        const data = await response.json();
                        this.formData.data[key] = data.value;
                        field.unmasked = true;
                    } else if (response.status === 403) {
                        alert('{{ __('You are not authorized to unmask this field.') }}');
                    }
                } catch (error) {
                    console.error('Error unmasking field:', error);
                }
            },
            async fetchSchema() {
                if (!this.formData.type) {
                    this.schema = {};
                    return;
                }
                const response = await fetch('/credentials/schema/' + encodeURIComponent(this.formData.type));
                const data = await response.json();
                this.schema = data.schema;

                // Initialize data for new keys in schema
                Object.keys(this.schema).forEach(key => {
                    this.schema[key].unmasked = false;
                    if (this.formData.data[key] === undefined) {
                        this.formData.data[key] = this.schema[key].default || '';
                    }
                });
            },
            prepareDataForSubmit() {
                let submittedData = {};
                Object.entries(this.formData.data).forEach(([key, value]) => {
                    const field = this.schema[key];
                    if (field) {
                        // For secrets, only include if not empty
                        if (field.secret) {
                            if (value !== '') {
                                submittedData[key] = value;
                            }
                        } else {
                            submittedData[key] = value;
                        }
                    }
                });
                return submittedData;
            },
            async showDevices(id, name) {
                this.selectedCredentialName = name;
                this.loadingDevices = true;
                this.devices = [];
                $(this.$refs.devicesModal).modal('show');

                try {
                    const response = await fetch(`/credentials/${id}/devices`);
                    if (response.ok) {
                        const data = await response.json();
                        this.devices = data.devices;
                    }
                } catch (error) {
                    console.error('Error fetching devices:', error);
                } finally {
                    this.loadingDevices = false;
                }
            }
        }
    }
</script>
@endpush
@endsection
