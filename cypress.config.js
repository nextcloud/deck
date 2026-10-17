import vue from '@vitejs/plugin-vue2'
import { defineConfig } from 'cypress'
import cypressSplit from 'cypress-split'
import vitePreprocessor from 'cypress-vite'
import { nodePolyfills } from 'vite-plugin-node-polyfills'

export default defineConfig({
	projectId: '1s7wkc',
	viewportWidth: 1280,
	viewportHeight: 720,
	e2e: {
		// We've imported your old cypress plugins here.
		// You may want to clean this up later by importing these.
		setupNodeEvents(on, config) {
			on(
				'file:preprocessor',
				vitePreprocessor({
					plugins: [vue(), nodePolyfills()],
					configFile: false,
				}),
			)
			cypressSplit(on, config)
			return config
		},
		baseUrl: 'http://nextcloud.local/index.php',
		specPattern: 'cypress/e2e/**/*.{js,jsx,ts,tsx}',
		experimentalMemoryManagement: true,
	},
})
