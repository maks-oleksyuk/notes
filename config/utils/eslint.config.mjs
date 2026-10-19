import core from '../../web/core/eslint.config.mjs';

export default [
  ...core,
  {
    ignores: [
      '.ddev/**/*',
      '.github/**/*',
      'app/themes/theme/**/*',
      'config/sync/**/*',
      'local/**/*',
      'var/**/*',
      'vendor/**/*',
      'web/**/*',
      'pnpm-lock.yaml',
      'taskfile.yml',
    ],
  },
  {
    files: ['**/*.{yml,yaml}'],
    rules: { 'prettier/prettier': 'off' },
  },
];
