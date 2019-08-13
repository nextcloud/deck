const merge = require('webpack-merge');
const common = require('./webpack.common.js');
const path = require('path');

module.exports = merge(common, {
  mode: 'development',
  devServer: {
    historyApiFallback: true,
    overlay: true,
    contentBase: path.join(__dirname, 'js'),
    writeToDisk: true
  },
  devtool: 'source-map',
})
