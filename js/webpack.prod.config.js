const webpack = require('webpack');
const merge = require('webpack-merge');
const baseConfig = require('./webpack.config.js');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin')


module.exports = merge(baseConfig, {
  mode: 'production',
  optimization: {
    minimizer: [
      new UglifyJsPlugin({
        test: /(vendor\.js)+/i
      })
    ]
  }});
