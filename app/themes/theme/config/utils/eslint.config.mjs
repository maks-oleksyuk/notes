import passing from '../../../../../web/core/eslint.passing.config.mjs';

export default [
  ...passing,
  { ignores: ['dist/**/*'] },
];
