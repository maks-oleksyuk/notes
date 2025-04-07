import { h } from 'vue';
import type { Theme } from 'vitepress';
import DefaultTheme from 'vitepress/theme';
import { enhanceAppWithTabs } from 'vitepress-plugin-tabs/client';
import vitepressBackToTop from 'vitepress-plugin-back-to-top';
import googleAnalytics from 'vitepress-plugin-google-analytics';

import {
  InjectionKey as NolebaseEnhancedReadabilitiesInjectionKey,
  LayoutMode as NolebaseEnhancedReadabilitiesLayoutMode,
  NolebaseEnhancedReadabilitiesMenu,
  Options as NolebaseEnhancedReadabilitiesOptions,
} from '@nolebase/vitepress-plugin-enhanced-readabilities/client';

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
    app.provide(NolebaseEnhancedReadabilitiesInjectionKey, {
      layoutSwitch: {
        defaultMode:
          NolebaseEnhancedReadabilitiesLayoutMode.SidebarWidthAdjustableOnly,
      },
      spotlight: {
        disabled: true,
      },
    } as NolebaseEnhancedReadabilitiesOptions);

    enhanceAppWithTabs(app);
    vitepressBackToTop({ threshold: 300 });
    googleAnalytics({ id: import.meta.env.VITE_GTAG });
  },
} satisfies Theme;
