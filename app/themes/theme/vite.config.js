import { globSync } from 'node:fs';
import { basename, relative } from 'node:path';
import autoprefixer from 'autoprefixer';
import postcssRtlLogicalProperties from 'postcss-rtl-logical-properties';
import { defineConfig } from 'vite';

// Every non-partial stylesheet becomes an entry. `style*` files land in
// dist/css/base/ to match app_theme.libraries.yml, everything else keeps its
// path relative to styles/.
const input = Object.fromEntries(
  globSync('styles/**/*.scss')
    .filter((file) => !basename(file).startsWith('_'))
    .map((file) => {
      const key = relative('styles', file).replace(/\.scss$/, '');
      return [key.startsWith('style') ? `base/${key}` : key, file];
    }),
);

export default defineConfig(({ mode }) => ({
  css: {
    postcss: {
      plugins: [autoprefixer(), postcssRtlLogicalProperties()],
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    sourcemap: mode !== 'production',
    rollupOptions: {
      input,
      output: {
        assetFileNames: 'css/[name][extname]',
      },
    },
  },
  plugins: [
    {
      // CSS-only entries still emit an empty JS chunk; drop it.
      name: 'drop-empty-js',
      generateBundle(_options, bundle) {
        for (const [file, chunk] of Object.entries(bundle)) {
          if (chunk.type === 'chunk' && chunk.code.trim().length <= 1) {
            delete bundle[file];
          }
        }
      },
    },
  ],
}));
