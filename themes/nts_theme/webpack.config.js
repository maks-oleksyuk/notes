import path from 'path';
import { glob } from 'glob';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';
import { CleanWebpackPlugin } from 'clean-webpack-plugin';

const dirname = process.cwd();
const isDev = process.env.NODE_ENV !== 'production';

const getEntries = () =>
  glob
    .sync('./styles/**/*.scss')
    .filter((file) => !path.basename(file).startsWith('_'))
    .reduce((entries, file) => {
      const key = path
        .relative('./styles', file)
        .replace(/\.(scss|css)$/, '')
        .replace(/\\/g, '/');
      entries[key.startsWith('style') ? `base/${key}` : key] = `./${file}`;
      return entries;
    }, {});

// mode: production development
export default {
  mode: 'development',
  entry: () => getEntries(),
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
    new CleanWebpackPlugin(),
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
