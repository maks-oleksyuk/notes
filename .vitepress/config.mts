import { defineConfig } from 'vitepress';
import { tabsMarkdownPlugin } from 'vitepress-plugin-tabs';

export default defineConfig({
  title: 'Notes',
  description: 'Just my notes and code snippets',
  base: '/notes',
  srcDir: './src',
  outDir: './dist',
  cleanUrls: true,
  lastUpdated: true,
  head: [
    ['link', { rel: 'icon', href: '/notes/favicon.ico' }],
    // [
    //   'script',
    //   {
    //     async: '',
    //     src: 'https://www.googletagmanager.com/gtag/js?id=G-L5YJQ5Q3TD',
    //   },
    // ],
    // [
    //   'script',
    //   {},
    //   "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\ngtag('config', 'G-L5YJQ5Q3TD');",
    // ],
  ],
  themeConfig: {
    nav: [{ text: 'Drupal', link: '/drupal' }],
    socialLinks: [
      { icon: 'github', link: '//github.com/maks-oleksyuk/notes' },
      { icon: 'linkedin', link: '//linkedin.com/in/maks-oleksyuk' },
    ],
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
});
