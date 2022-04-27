<template>
	<NcModal size="normal"
		data-text-el="formatting-help"
		:name="t('deck', 'Keyboard shortcuts')"
		@close="$emit('close')">
		<div class="help-modal">
			<h2>{{ t('deck', 'Keyboard shortcuts') }}</h2>
			<p>{{ t('deck', 'Speed up using Deck with simple shortcuts.') }}</p>

			<h3>{{ t('deck', 'Board actions') }}</h3>
			<table>
				<thead>
					<tr>
						<th>{{ t('deck', 'Keyboard shortcut') }}</th>
						<th>{{ t('deck', 'Action') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<kbd>{{ t('deck', 'Shift') }}</kbd>
							+
							<kbd>{{ t('deck', 'Scroll') }}</kbd>
						</td>
						<td>
							{{ t('deck', 'Scroll sideways') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>←</kbd>
							<kbd>→</kbd>
							<kbd>↑</kbd>
							<kbd>↓</kbd>
						</td>
						<td>
							{{ t('deck', 'Navigate between cards') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>{{ t('deck', 'Esc') }}</kbd>
						</td>
						<td>
							{{ t('deck', 'Close card details') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>{{ t('deck', 'Ctrl') }}</kbd>
							<kbd>F</kbd>
						</td>
						<td>
							{{ t('deck', 'Search') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>F</kbd>
						</td>
						<td>
							{{ t('deck', 'Show card filters') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>X</kbd>
						</td>
						<td>
							{{ t('deck', 'Clear card filters') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>?</kbd>
						</td>
						<td>
							{{ t('deck', 'Show help dialog') }}
						</td>
					</tr>
				</tbody>
			</table>

			<h3>{{ t('deck', 'Card actions') }}</h3>
			<p>{{ t('deck', 'The following actions can be triggered on the currently highlighted card') }}</p>
			<table>
				<thead>
					<tr>
						<th>{{ t('deck', 'Keyboard shortcut') }}</th>
						<th>{{ t('deck', 'Action') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<kbd>{{ t('deck', 'Enter') }}</kbd>
							<kbd>{{ t('deck', 'Space') }}</kbd>
						</td>
						<td>
							{{ t('deck', 'Open card details') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>E</kbd>
						</td>
						<td>
							{{ t('deck', 'Edit the card title') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>S</kbd>
						</td>
						<td>
							{{ t('deck', 'Assign yorself to the current card') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>A</kbd>
						</td>
						<td>
							{{ t('deck', 'Archive/unarchive the current card') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>O</kbd>
						</td>
						<td>
							{{ t('deck', 'Mark card as completed/not completed') }}
						</td>
					</tr>
					<tr>
						<td>
							<kbd>M</kbd>
						</td>
						<td>
							{{ t('deck', 'Open card menu') }}
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, Tooltip } from '@nextcloud/vue'

export default {
	name: 'HelpModal',
	components: {
		NcModal,
	},
	directives: {
		Tooltip,
	},
}
</script>

<style lang="scss" scoped>
	.help-modal {
		width: max-content;
		padding: 30px 40px 20px;
		user-select: text;

		h2, h3 {
			font-weight: bold;
		}
	}

	table {
		margin-top: 24px;
		border-collapse: collapse;

		tbody tr {
			&:hover, &:focus, &:active {
				background-color: transparent !important;
			}
		}

		thead tr {
			border: none;
		}

		th {
			font-weight: bold;
			padding: .75rem 1rem .75rem 0;
			border-bottom: 2px solid var(--color-background-darker);
		}

		td {
			padding: .75rem 1rem .75rem 0;
			border-top: 1px solid var(--color-background-dark);
			border-bottom: unset;

			&.noborder {
				border-top: unset;
			}

			&.ellipsis_top {
				padding-bottom: 0;
			}

			&.ellipsis {
				padding-top: 0;
				padding-bottom: 0;
			}

			&.ellipsis_bottom {
				padding-top: 0;
			}
		}

		kbd {
			font-size: smaller;
		}

		code {
			padding: .2em .4em;
			font-size: 90%;
			background-color: var(--color-background-dark);
			border-radius: 6px;
		}
	}
</style>
