<!--
  - ButtonGroup.vue
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

<template>
    <div>
        <slot style="display: none"></slot>
        <button
                v-for="option in options"
                type="button"
                :class="getClass(option.value)"
                @click="setActive(option.value)"
        >{{ option.text }}
        </button>
    </div>
</template>

<script>
    export default {
        name: "ButtonGroup",
        data() {
            return {
                options: [],
                active: null,
                classes: null
            }
        },
        methods: {
            setActive(value) {
                this.active = value;
                this.$emit('input', value);
            },
            getClass(value) {
                return this.classes.join(' ') + (this.active === value ? ' active' : '')
            }
        },
        mounted() {
            let buttons = this.$el.getElementsByTagName('button');
            for (let button of buttons) {
                this.options.push({
                    text: button.innerHTML,
                    value: button.value,
                });
                if (button.classList.contains('active')) {
                    this.active = button.value
                } else if (this.classes === null) {
                    this.classes = [...button.classList]
                }
            }
            [...buttons].forEach(el => el.remove())
        }
    }
</script>

<style scoped>

</style>