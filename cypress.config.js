const { defineConfig } = require('cypress')
const cypressSplit = require('cypress-split')

module.exports = defineConfig({
	projectId: '1s7wkc',
	viewportWidth: 1280,
	viewportHeight: 720,
	e2e: {
		// We've imported your old cypress plugins here.
		// You may want to clean this up later by importing these.
		setupNodeEvents(on, config) {
			cypressSplit(on, config)
			require('./cypress/plugins/index.js')(on, config)
			return config
		},
		baseUrl: 'http://nextcloud.local/index.php',
		specPattern: 'cypress/e2e/**/*.{js,jsx,ts,tsx}',
		experimentalMemoryManagement: true,
	},
})
