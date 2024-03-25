import { h } from 'vue';
import Theme from 'vitepress/theme';
import { enhanceAppWithTabs } from 'vitepress-plugin-tabs/client';
import vitepressBackToTop from 'vitepress-plugin-back-to-top';
import googleAnalytics from 'vitepress-plugin-google-analytics';

import './styles/vars.css';
import './styles/base.css';
import './styles/components/back-to-top.css';

export default {
  extends: Theme,
  Layout: () => {
    return h(Theme.Layout, null, {});
  },
  enhanceApp({ app }) {
    enhanceAppWithTabs(app);
    vitepressBackToTop({ threshold: 300 });
    googleAnalytics({ id: 'G-L5YJQ5Q3TD' });
  },
};
