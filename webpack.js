const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpack = require('webpack')
const path = require('path')

const buildMode = process.env.NODE_ENV
const isDevServer = process.env.WEBPACK_SERVE

webpackConfig.entry = {
	...webpackConfig.entry,
	collections: path.join(__dirname, 'src', 'init-collections.js'),
	dashboard: path.join(__dirname, 'src', 'init-dashboard.js'),
	calendar: path.join(__dirname, 'src', 'init-calendar.js'),
	talk: path.join(__dirname, 'src', 'init-talk.js'),
	reference: path.join(__dirname, 'src', 'init-reference.js'),
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
webpackConfig.resolve.alias = {}

module.exports = webpackConfig
