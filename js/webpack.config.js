const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
  node: {
    fs: 'empty',
  },
  entry: {
    deck: ['./init.js'],
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/build'
  },
  resolve: {
    modules: [path.resolve(__dirname), 'node_modules'],
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
        query: {
	  presets: ['@babel/preset-env'],
        }
      },
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader'
        ]
      }
    ]
  },
  optimization: {
    splitChunks: {
      cacheGroups: {
        /* separate vendor chunk for node_modules and legacy scripts */
        commons: {
          test: /[\\/]node_modules[\\/]/,
          name: 'vendor',
          chunks: 'all'
        },
        legacy: {
          test: /[\\/]legacy[\\/]/,
          name: 'vendor',
          chunks: 'all'
        }
      }
    }
  },
  /* use external jQuery from server */
  externals: {
    'jquery': 'jQuery'
  },
  plugins: [
    new MiniCssExtractPlugin('[name].css'),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery'
    })
  ]
};
