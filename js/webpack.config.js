const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	node: {
		fs: 'empty',
	},
	entry: {
		deck: ['./init.js'],
		//collections: ['./src/init-collections.js']
	},
	output: {
		filename: '[name].js',
		path: __dirname + '/build'
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'style-loader', 'css-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /\.js$/,
				exclude: /node_modules/,
				loader: 'babel-loader',
				query: {
					presets: ['@babel/preset-env'],
					plugins: ['@babel/plugin-syntax-dynamic-import']
				}
			},
			{
				test: /\.scss$/,
				use: [
					'vue-style-loader',
					'css-loader',
					'sass-loader'
				]
			},
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'url-loader',
				options: {
					name: '[name].[ext]?[hash]'
				}
			},
		]
	},
	/* use external jQuery from server */
	externals: {
		'jquery': 'jQuery'
	},
	resolve: {
		alias: {
			vue$: 'vue/dist/vue.esm.js'
		},
		extensions: ['*', '.js', '.vue', '.json'],
		modules: [
			path.resolve(__dirname),
			path.join(__dirname, 'node_modules'),
			'node_modules'
		]
	},
	plugins: [
		new VueLoaderPlugin(),
		new webpack.ProvidePlugin({
			$: 'jquery',
			jQuery: 'jquery'
		})
	]
};
