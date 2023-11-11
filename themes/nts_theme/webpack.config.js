import path from 'path';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';

const dirname = process.cwd();
const isDev = process.env.NODE_ENV !== 'production';

// mode: production development

export default {
  mode: 'development',
  entry: {
    // Base
    'base/style': ['./styles/style.scss'],
  },
  output: {
    filename: 'js/[name].js',
    path: path.resolve(dirname, 'dist'),
    pathinfo: false,
    publicPath: '../../',
  },
  module: {
    rules: [
      {
        test: /\.(css|scss)$/,
        use: [MiniCssExtractPlugin.loader, 'css-loader', 'sass-loader'],
      },
    ],
  },
  plugins: [
    new RemoveEmptyScriptsPlugin(),
    new MiniCssExtractPlugin({
      filename: 'css/[name].css',
    }),
  ],
  stats: {
    colors: true,
    hash: false,
    version: true,
    timings: true,
    assets: false,
    chunks: false,
    modules: false,
    reasons: false,
    children: false,
    source: true,
    errors: true,
    errorDetails: true,
    warnings: true,
    publicPath: false,
  },
};
