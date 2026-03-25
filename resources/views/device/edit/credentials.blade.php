@extends('layouts.librenmsv1')

@section('content')
    <x-device.page :device="$device">
        <x-device.edit-tabs :device="$device" tab="credentials" />

        <form id="edit-credentials" method="post" action="{{ route('device.edit.update', [$device->device_id]) }}" class="form-horizontal" x-data="deviceCredentialManager()">
            @method('PUT')
            @csrf

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{{ __('Associated Credentials') }}</h3>
                </div>
                <div class="panel-body">
                    <p class="help-block col-sm-offset-3 col-sm-9">{{ __('These credentials are explicitly associated with this device and will be used for polling and discovery.') }}</p>

                    <div id="credentials-selection-container">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{ __('Available Credentials') }}</label>
                            <div class="col-sm-6">
                                <select id="credential_selector" class="form-control select2" style="width: 100%" x-ref="selector">
                                    <option value="">{{ __('Select a credential to add...') }}</option>
                                    @foreach($all_credentials as $credential)
                                        @php $type = $credential->getCredentialType(); @endphp
                                        <option value="{{ $credential->id }}" data-name="{{ $credential->name }}" data-type="{{ $type ? $type->name() : $credential->type }}">
                                            {{ $credential->name }} ({{ $type ? $type->name() : $credential->type }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-3">
                                <button type="button" class="btn btn-success" @click="addCredential()">
                                    <i class="fa fa-plus"></i> {{ __('Add') }}
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-9">
                                <table class="table table-striped table-hover" id="selected-credentials-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px"></th>
                                            <th>{{ __('Name') }}</th>
                                            <th>{{ __('Type') }}</th>
                                            <th style="width: 100px">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="selected-credentials-body" x-ref="sortable">
                                        <template x-for="credential in selectedCredentials" :key="credential.id">
                                            <tr :data-id="credential.id">
                                                <td><i class="fa fa-reorder handle tw:cursor-move"></i></td>
                                                <td x-text="credential.name"></td>
                                                <td x-text="credential.type"></td>
                                                <td>
                                                    <input type="hidden" name="secure_credentials[]" :value="credential.id">
                                                    <button type="button" class="btn btn-danger btn-xs" @click="removeCredential(credential.id)">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <p class="help-block">{{ __('Drag the handle to re-order credentials. They will be tried in the order listed.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> {{ __('Save Changes') }}
                    </button>
                </div>
            </div>
        </form>
    </x-device.page>
@endsection

@push('scripts')
    <script src="{{ asset('js/sortable.min.js') }}"></script>
    <script>
        function deviceCredentialManager() {
            return {
                selectedCredentials: [
                    @foreach($current_credentials as $credential)
                        @php $type = $credential->getCredentialType(); @endphp
                        {
                            id: {{ $credential->id }},
                            name: '{{ addslashes($credential->name) }}',
                            type: '{{ addslashes($type ? $type->name() : $credential->type) }}'
                        },
                    @endforeach
                ],
                init() {
                    this.$nextTick(() => {
                        $(this.$refs.selector).select2();

                        new Sortable(this.$refs.sortable, {
                            handle: '.handle',
                            animation: 150,
                            onEnd: (evt) => {
                                // Re-order the array based on DOM change
                                const newOrder = Array.from(this.$refs.sortable.querySelectorAll('tr')).map(tr => parseInt(tr.dataset.id));
                                this.selectedCredentials.sort((a, b) => newOrder.indexOf(a.id) - newOrder.indexOf(b.id));
                            }
                        });
                    });
                },
                addCredential() {
                    const selector = $(this.$refs.selector);
                    const id = parseInt(selector.val());
                    if (!id) return;

                    if (this.selectedCredentials.find(c => c.id === id)) {
                        toastr.warning('{{ __('Credential already added') }}');
                        return;
                    }

                    const option = selector.find(':selected');
                    this.selectedCredentials.push({
                        id: id,
                        name: option.data('name'),
                        type: option.data('type')
                    });

                    selector.val('').trigger('change');
                },
                removeCredential(id) {
                    this.selectedCredentials = this.selectedCredentials.filter(c => c.id !== id);
                }
            }
        }
    </script>
@endpush
