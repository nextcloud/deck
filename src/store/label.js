import Vue from 'vue'

export default {
	state: {
		labels: []
	},
	getters: {
		labelByCurrentBoard() {
			console.log(state)
			return state.currentBoard.labels;
		},
		labelById: state => (id) => {
			//return state.cards.find((card) => card.id === id)
		}
	},
	mutations: {
		addLabel(state, label) {
			
		},
		updateLabel(state, label) {

		},
		removeLabel(state, id) {
			
			
			console.log(this.state.labels);


			this.state.labels = this.state.labels.filter((l) => {
				return id !== l.id
			})

			console.log(this.state.labels);
			
		},
		
	},
	actions: {
		removeLabel({ commit }, id) {
			commit('removeLabel', id)
		},
	}
}
