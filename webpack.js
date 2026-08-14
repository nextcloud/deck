const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpack = require('webpack')
const path = require('path')

const buildMode = process.env.NODE_ENV
const isDevServer = process.env.WEBPACK_SERVE

webpackConfig.entry = {
	...webpackConfig.entry,
	// collections: path.join(__dirname, 'src', 'init-collections.js'),
	// dashboard: path.join(__dirname, 'src', 'init-dashboard.js'),
	// calendar: path.join(__dirname, 'src', 'init-calendar.js'),
	// talk: path.join(__dirname, 'src', 'init-talk.js'),
	// reference: path.join(__dirname, 'src', 'init-reference.js'),
}

if (isDevServer) {
	webpackConfig.output.publicPath = 'http://127.0.0.1:3000/'
	webpackConfig.plugins.push(
		new webpack.DefinePlugin({
			'process.env.WEBPACK_SERVE': true,
		})
	)
} else {
	webpackConfig.stats = {
		context: path.resolve(__dirname, 'src'),
		assets: true,
		entrypoints: true,
		chunks: true,
		modules: true,
	}
}
// Workaround for https://github.com/nextcloud/webpack-vue-config/pull/432 causing problems with nextcloud-vue-collections
webpackConfig.resolve.alias = {
	...(webpackConfig.resolve.alias || {}),
	vue$: '@vue/compat',
}

webpackConfig.plugins.push(
	new webpack.DefinePlugin({
		__VUE_OPTIONS_API__: true,
		__VUE_PROD_DEVTOOLS__: false,
		__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: buildMode !== 'production',
	})
)

const vueRule = webpackConfig.module?.rules?.find((rule) => {
	if (!Array.isArray(rule.use)) {
		return false
	}

	return rule.use.some((use) => {
		if (typeof use === 'string') {
			return use.includes('vue-loader')
		}

		return use?.loader?.includes('vue-loader')
	})
})

if (vueRule?.use) {
	const vueLoaderUse = vueRule.use.find((use) => {
		if (typeof use === 'string') {
			return use.includes('vue-loader')
		}

		return use?.loader?.includes('vue-loader')
	})

	if (vueLoaderUse && typeof vueLoaderUse === 'object') {
		vueLoaderUse.options = {
			...(vueLoaderUse.options || {}),
			compilerOptions: {
				...(vueLoaderUse.options?.compilerOptions || {}),
				compatConfig: {
					MODE: 2,
					COMPONENT_V_MODEL: false,
				},
			},
		}
	}
}

// Allow importing frappe-gantt CSS (not exposed via package.json exports field)
webpackConfig.resolve.alias['frappe-gantt/dist/frappe-gantt.css'] = path.resolve(__dirname, 'node_modules/frappe-gantt/dist/frappe-gantt.css')

module.exports = webpackConfig
