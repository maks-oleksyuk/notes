import { h } from 'vue';
import Theme from 'vitepress/theme';
import './style.css';
import { enhanceAppWithTabs } from 'vitepress-plugin-tabs/client';

export default {
  extends: Theme,
  Layout: () => {
    return h(Theme.Layout, null, {});
  },
  enhanceApp({ app }) {
    enhanceAppWithTabs(app);
  },
};
