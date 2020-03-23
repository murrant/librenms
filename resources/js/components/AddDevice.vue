<!--
  - AddDevice.vue
  -
  - Description-
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation, either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  - @package    LibreNMS
  - @link       http://librenms.org
  - @copyright  2020 Tony Murray
  - @author     Tony Murray <murraytony@gmail.com>
  -->

<script>
    export default {
        name: "AddDevice",
        props: {
            data: {type: Object}
        },
        data() {
            return {
                advanced: this.data.advanced,
                results: [{hostname: 'one', status: 'failed'}, {hostname: 'two', status: 'pending'}, {hostname: 'three', status: 'success', device_id: 42}],
                hostname: null,
                override_ip: null,
                poller_group: this.data.default_poller_group,
                type: 'snmpv2',
                port: null,
                proto: 'udp',
                transport: '4',
                community: null,
                sysname: null,
                os: null,
                hardware: null,
                port_association: this.data.port_association,
                auth_level: 'noAuthNoPriv',
                auth_algo: 'MD5',
                auth_name: null,
                auth_pass: null,
                crypto_algo: 'AES',
                crypto_pass: null
            }
        },
        methods: {
            activeClass(active) {
                return active ? 'active list-bold' : ''
            },
            toggleAdvanced(event) {
                this.advanced = event.value;
                axios.post(route('preferences.store'), {pref: 'device_add_advanced', value: event.value})
                    .catch((error) => {
                        console.log('Failed to toggle advanced persistent preference')
                    })
            },
            findResult(hostname) {
                return this.results.find( result => result['hostname'] === hostname)
            },
            resultStatusClass(status) {
                if (status === 'pending') {
                    return 'alert-info'
                }

                return status === 'success' ? 'alert-success' : 'alert-danger'
            },
            addDevice(event) {
                let formData = {
                    hostname: this.hostname,
                    override_ip: this.override_ip,
                    poller_group: this.poller_group,
                    type: this.type,
                    port: this.port,
                    proto: this.proto,
                    transport: this.transport,
                    community: this.community,
                    sysname: this.sysname,
                    os: this.os,
                    hardware: this.hardware,
                    port_association: this.port_association,
                    auth_level: this.auth_level,
                    auth_algo: this.auth_algo,
                    auth_name: this.auth_name,
                    auth_pass: this.auth_pass,
                    crypto_algo: this.crypto_algo,
                    crypto_pass: this.crypto_pass
                };

                let existing = this.findResult(this.hostname);
                if (existing) {
                    existing['data'] = formData;
                    existing['status'] = 'pending';
                } else {
                    this.results.unshift({
                        hostname: this.hostname,
                        data: formData,
                        status: 'pending'
                    });
                }

                axios.post(route('device.store'), formData)
                    .then((event) => {
                        let pending = this.findResult(event.data.hostname);
                        pending['status'] = 'success';
                        pending['device_id'] = event.data.device_id;
                    })
                    .catch((event) => {
                        let pending = this.findResult(event.data.hostname);
                        pending['status'] = 'failed';

                    });
            }
        }
    }
</script>

<style>
</style>