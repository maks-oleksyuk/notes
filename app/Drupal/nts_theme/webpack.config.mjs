import autoprefixer from 'autoprefixer';
import { CleanWebpackPlugin } from 'clean-webpack-plugin';
import { glob } from 'glob';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import path from 'path';
import postcssRTLCSS from 'postcss-rtlcss';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';
import webpack from 'webpack';

const { SourceMapDevToolPlugin } = webpack;
const dirname = process.cwd();
const isDev = process.env.NODE_ENV !== 'production';

const getEntries = () => {
  return glob
    .sync('./styles/**/*.scss')
    .filter((file) => !path.basename(file).startsWith('_'))
    .reduce((acc, file) => {
      const entries = { ...acc };
      const key = path
        .relative('./styles', file)
        .replace(/\.(scss|css)$/, '')
        .replace(/\\/g, '/');
      entries[key.startsWith('style') ? `base/${key}` : key] = `./${file}`;
      return entries;
    }, {});
};

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
        test: /\.(png|jpe?g|gif|svg)$/,
        type: 'asset/resource',
        generator: {
          outputPath: '../',
          filename: '[path][name][ext]',
        },
      },
      {
        test: /\.(css|scss)$/,
        use: [
          {
            loader: MiniCssExtractPlugin.loader,
            options: {
              publicPath: (resourcePath, context) => {
                const relativePath = path.relative(resourcePath, context);
                return resourcePath.includes('style.scss')
                  ? `../${relativePath}/`
                  : `${relativePath}/`;
              },
            },
          },
          {
            loader: 'css-loader',
            options: {
              sourceMap: isDev,
              importLoaders: 2,
            },
          },
          {
            loader: 'postcss-loader',
            options: {
              sourceMap: isDev,
              postcssOptions: {
                plugins: [
                  autoprefixer(),
                  postcssRTLCSS(),
                  [
                    'postcss-perfectionist',
                    {
                      format: 'expanded',
                      indentSize: 2,
                      trimLeadingZero: true,
                      zeroLengthNoUnit: false,
                      maxAtRuleLength: false,
                      maxSelectorLength: false,
                      maxValueLength: false,
                    },
                  ],
                ],
              },
            },
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: isDev,
            },
          },
        ],
      },
      {
        test: /\.(woff(2))(\?v=\d+\.\d+\.\d+)?$/,
        type: 'asset/resource',
        generator: {
          outputPath: '../',
          filename: '[path][name][ext]',
        },
      },
    ],
  },
  resolve: {
    alias: {
      media: path.join(dirname, 'media'),
      icons: path.join(dirname, 'media/icons'),
      images: path.join(dirname, 'media/images'),
      fonts: path.join(dirname, 'media/fonts'),
    },
    modules: [path.join(dirname, 'node_modules')],
  },
  plugins: [
    new RemoveEmptyScriptsPlugin(),
    new CleanWebpackPlugin({
      cleanStaleWebpackAssets: false,
    }),
    new MiniCssExtractPlugin({
      filename: 'css/[name].css',
    }),
    new SourceMapDevToolPlugin({
      filename: '[file].map',
    }),
  ],
  stats: {
    preset: 'minimal',
    assets: false,
    modules: false,
  },
};
