/*
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
 */

// nav stuff
// todo maybe move out of nav.js directly and import here

import { translate as t } from 'nextcloud-server/dist/l10n'

let defaultCategories = [
    {
        id: 'deck-boards',
        classes: [],
        icon: 'icon-deck',
        text: t('deck', 'All boards'),
        router: {
            name: 'boards'
        }
    },
    {
        id: 'deck-boards-archived',
        classes: [],
        icon: 'icon-archive',
        text: t('deck', 'Archived boards'),
        router: {
            name: 'boards.archived'
        }
    },
    {
        id: 'deck-boards-shared',
        classes: [],
        icon: 'icon-shared',
        text: t('deck', 'Shared boards'),
        router: {
            name: 'boards.shared'
        }
    }
]

const boardActions = [
    {
        action: () => {},
        icon: 'icon-edit',
        text: t('deck', 'Edit board')
    },
    {
        action: () => {},
        icon: 'icon-archive',
        text: t('deck', 'Archive board')
    },
    {
        action: () => {},
        icon: 'icon-delete',
        text: t('deck', 'Delete board')
    },
    {
        action: () => {},
        icon: 'icon-settings',
        text: t('deck', 'Board details')
    }
]

const boards = [
    {
        id: 'deck-board-1',
        classes: [],
        bullet: '#00cc00',
        text: 'Example board',
        router: {
            name: 'board',
            params: { id: 1 }
        },
        utils: {
            actions: boardActions
        }
    }
]

const addButton = {
    icon: 'icon-add',
    text: t('deck', 'Create new board'),
    action: () => {}
}

// initial state
const state = {
    hidden: false,
    menu: {
        items: defaultCategories.concat(boards).concat([addButton]),
        loading: false
    }
}

// getters
const getters = {}

// actions
const actions = {
    toggle ({ commit }) {
        commit('toggle')
    }
}

// mutations
const mutations = {
    toggle (state) {
        state.hidden = !state.hidden
    }
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}
