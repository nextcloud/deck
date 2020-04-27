const webpack = require('webpack');
const merge = require('webpack-merge');
const dev = require('./webpack.dev.js');

module.exports = merge(dev, {
	devServer: {
		hot: true,
		port: 3000,
		/**
		 * This makes sure the main entrypoint is written to disk so it is
		 * loaded by Nextcloud though our existing addScript calls
		 */
		writeToDisk: (filePath) => {
			return /deck\.js$/.test(filePath);
		},
		headers: {
			'Access-Control-Allow-Origin': '*'
		}
	},
	plugins: [
		new webpack.DefinePlugin({
			'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV),
			'process.env.HOT': true
		})
	]
})
