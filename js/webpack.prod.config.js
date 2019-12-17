const webpack = require('webpack');
const merge = require('webpack-merge');
const baseConfig = require('./webpack.config.js');
const TerserPlugin = require('terser-webpack-plugin');


module.exports = merge(baseConfig, {
  mode: 'production',
  devtool: '#source-map',
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin({
        test: /vendor\.js(\?.*)?$/i,
      }),
    ],
  },
});
