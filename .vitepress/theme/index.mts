import { h } from 'vue';
import DefaultTheme from 'vitepress/theme';
import { enhanceAppWithTabs } from 'vitepress-plugin-tabs/client';
import vitepressBackToTop from 'vitepress-plugin-back-to-top';
import googleAnalytics from 'vitepress-plugin-google-analytics';
import { NolebaseEnhancedReadabilitiesMenu } from '@nolebase/vitepress-plugin-enhanced-readabilities/client';

import './styles/vars.css';
import './styles/base.css';
import './styles/components/vp-doc.css';
import './styles/components/back-to-top.css';
import '@nolebase/vitepress-plugin-enhanced-readabilities/client/style.css';

export default {
  extends: DefaultTheme,
  Layout: () => {
    return h(DefaultTheme.Layout, null, {
      'nav-bar-content-after': () => h(NolebaseEnhancedReadabilitiesMenu),
    });
  },
  enhanceApp({ app }) {
    enhanceAppWithTabs(app);
    vitepressBackToTop({ threshold: 300 });
    googleAnalytics({ id: import.meta.env.VITE_GTAG });
  },
};
