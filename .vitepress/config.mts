import { defineConfig } from 'vitepress';
import { generateSidebar } from 'vitepress-sidebar';
import { tabsMarkdownPlugin } from 'vitepress-plugin-tabs';

export default defineConfig({
  title: 'Notes',
  description: 'Just my notes and code snippets',
  base: '/notes',
  srcDir: './src',
  outDir: './dist',
  cleanUrls: true,
  lastUpdated: true,
  head: [['link', { rel: 'icon', href: '/notes/favicon.ico' }]],
  themeConfig: {
    nav: [{ text: 'Drupal', link: '/drupal/' }],
    socialLinks: [
      { icon: 'github', link: '//github.com/maks-oleksyuk/notes' },
      { icon: 'linkedin', link: '//linkedin.com/in/maks-oleksyuk' },
    ],
    sidebar: generateSidebar([
      {
        documentRootPath: 'src',
        scanStartPath: 'drupal',
        resolvePath: '/drupal/',
        collapsed: true,
        useTitleFromFrontmatter: true,
        useFolderLinkFromIndexFile: true,
        useFolderTitleFromIndexFile: true,
      },
    ]),
  },
  locales: {
    root: {
      label: 'English',
      lang: 'en',
    },
    ua: {
      label: 'Українська',
      lang: 'uk',
      themeConfig: {
        nav: [{ text: 'Buses', link: '/ua/other/bus' }],
        darkModeSwitchLabel: 'Зовнішній вигляд',
        returnToTopLabel: 'Повернутись до початку',
        outline: {
          label: 'На цій сторінці',
        },
        lastUpdated: {
          text: 'Останнє оновлення',
        },
      },
    },
  },
  markdown: {
    config(md) {
      md.use(tabsMarkdownPlugin);
    },
  },
  vite: {
    ssr: {
      noExternal: ['@nolebase/vitepress-plugin-enhanced-readabilities'],
    },
  },
});
