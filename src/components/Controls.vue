<!--
* @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
*
* @author Michael Weimann <mail@michael-weimann.eu>
*
* @license GNU AGPL version 3 or any later version
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
-->

<template>

<div>
    <div id="app-navigation-toggle-custom" class="icon-menu" v-on:click="toggleNav"></div>
</div>

</template>

<script>
export default {
    name: "Controls",
    methods: {
        toggleNav() {
            this.$store.dispatch('nav/toggle')
        }
    }
}
</script>

<style lang="scss" scoped>

</style>
