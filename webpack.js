const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path');
const { merge } = require('webpack-merge')

const config = {
	entry: {
		deck: path.join(__dirname, 'src', 'main.js'),
		collections: path.join(__dirname, 'src', 'init-collections.js'),
		dashboard: path.join(__dirname, 'src', 'init-dashboard.js'),
	},
	output: {
		filename: '[name].js',
		jsonpFunction: 'webpackJsonpOCADeck',
		chunkFilename: '[name].js?v=[contenthash]',
	},
	module: {
		rules: [
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'url-loader',
				options: {
					name: '[name].[ext]?[hash]'
				}
			}
		]
	},
	resolve: {
		extensions: ['*', '.js', '.vue', '.json'],
		modules: [
			path.resolve(__dirname, 'node_modules'),
			'node_modules'
		]
	}
};

module.exports = merge(webpackConfig, config)

