/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const sampleBoard = (title = 'MyTestBoard') => {
	return {
		title: title,
		color: '00ff00',
		stacks: [
			{
				title: 'TestList',
				cards: [
					{
						title: 'Hello world',
						description: '# Hello world',
					},
				],
			},
		],
	}
}
