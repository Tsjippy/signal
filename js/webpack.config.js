// webpack.config.js
const path = require('path');
const sharedAliases = require('../../tsjippy-shared-functionality/js/webpack.aliases'); // Import your aliases

module.exports = {
  mode: 'production',
  devtool: 'source-map',
  // You can define all your files as entry points here
  entry: {
    admin: './admin.js',
    signal: './signal.js',
  },
  output: {
    path: path.resolve(__dirname, '.'),
    filename: '[name].min.js', // Automatically uses the entry key name (e.g., main.min.js)
  },
  resolve: {
    alias: {
        ...sharedAliases,
    },
  },
};