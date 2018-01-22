const path = require('path');
const webpack = require('webpack');
const ExtractTextPlugin = require("extract-text-webpack-plugin");

module.exports = {
  entry: {
    deck: './init.js',
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/build'
  },
  resolve: {
    modules: [path.resolve(__dirname), 'node_modules'],
  },
  module: {
    loaders: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
      },
      {
        test: /\.css$/,
        use: ExtractTextPlugin.extract({
          use: {
            loader: 'css-loader',
            options: {
              minimize: true,
            }
          },
        })
      }
    ]
  },
  plugins: [
    new webpack.optimize.UglifyJsPlugin({
      test: /(vendor\.js)+/i,
    }),
    // we do not uglify deck.js since there are no proper dependency annotations
    new webpack.optimize.CommonsChunkPlugin({
      name: 'vendor',
      filename: 'vendor.js',
      minChunks(module, count) {
        var context = module.context;
        return context && context.indexOf('node_modules') >= 0;
      },
    }),
    new ExtractTextPlugin({
      filename: "../../css/vendor.css",
      allChunks: true
    }),
  ]
};
