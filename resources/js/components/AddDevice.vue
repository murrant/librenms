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
                results: [{hostname: 'one', status: 'failed'}, {hostname: 'two', status: 'success'}],
                hostname: null,
                override_ip: null,
                type: 'snmpv2',
                poller_group: this.data.default_poller_group,
                port: null,
                proto: 'udp',
                transport: '4',
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
            addDevice(event) {
                console.log(event);
                let formData = {
                    hostname: this.hostname,
                    override_ip: this.override_ip,
                    type: this.type,
                    port: this.port,
                    proto: this.proto,
                    transport: this.transport,
                    sysname: this.sysname,
                    os: this.os,
                    hardware: this.hardware,
                    port_association: this.port_association,
                    auth_level: this.auth_level,
                    auth_algo: this.auth_algo,
                    auth_name: this.auth_name,
                    auth_pass: this.auth_pass,
                    crypto_algo: this.crypto_algo,
                    crypt_pass: this.crypt_pass
                };

                axios.post(route('device.store'), formData)
                    .then((event) => {
                        console.log(event)
                    })
                    .catch((event) => {
                        console.log(event)
                    });
            }
        }
    }
</script>

<style>
</style>