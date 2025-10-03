import { defineConfig } from 'vitepress';
import { generateSidebar } from 'vitepress-sidebar';
import { tabsMarkdownPlugin } from 'vitepress-plugin-tabs';
import {
  groupIconMdPlugin,
  groupIconVitePlugin,
} from 'vitepress-plugin-group-icons';

const devServerUrl =
  process.env.DDEV_PRIMARY_URL ?
    `${process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, '')}:5173`
  : process.env.VITE_DEV_SERVER_URL || 'http://localhost:5173';

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
    [
      'script',
      {
        async: true,
        src: 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1549697176150032',
        crossorigin: 'anonymous',
      },
    ],
  ],
  themeConfig: {
    nav: [
      { text: 'Drupal', link: '/drupal/' },
      { text: 'Laravel', link: '/laravel/' },
      { text: 'mono', link: '//send.monobank.ua/jar/f82PN8AyZ' },
    ],
    socialLinks: [
      { icon: 'github', link: '//github.com/maks-oleksyuk/notes' },
      { icon: 'linkedin', link: '//linkedin.com/in/maks-oleksyuk' },
      { icon: 'telegram', link: '//t.me/maks_oleksyuk' },
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
      {
        documentRootPath: 'src',
        scanStartPath: 'laravel',
        resolvePath: '/laravel/',
        collapsed: true,
        useTitleFromFrontmatter: true,
        useFolderLinkFromIndexFile: true,
        useFolderTitleFromIndexFile: true,
      },
      {
        documentRootPath: 'src',
        scanStartPath: 'ua/other/bus',
        resolvePath: '/ua/other/bus/',
        collapsed: true,
        useTitleFromFrontmatter: true,
        useFolderLinkFromIndexFile: true,
        useFolderTitleFromIndexFile: true,
        frontmatterTitleFieldName: 'menu_title',
        manualSortFileNameByPriority: [
          'lutsk.md',
          'kivertsi.md',
          'ozero.md',
          '103.md',
        ],
      },
    ]),
    externalLinkIcon: true,
  },
  locales: {
    root: {
      label: '🇺🇸 English',
      lang: 'en',
    },
    ua: {
      label: '🇺🇦 Українська',
      lang: 'uk',
      themeConfig: {
        nav: [
          { text: 'Buses', link: '/ua/other/bus/' },
          { text: 'mono', link: '//send.monobank.ua/jar/f82PN8AyZ' },
        ],
        darkModeSwitchLabel: 'Зовнішній вигляд',
        returnToTopLabel: 'Повернутись до початку',
        outline: {
          label: 'На цій сторінці',
        },
        lastUpdated: {
          text: 'Останнє оновлення',
        },
        docFooter: {
          prev: 'Попередня сторінка',
          next: 'Наступна сторінка',
        },
      },
    },
  },
  markdown: {
    config(md) {
      md.use(tabsMarkdownPlugin);
      md.use(groupIconMdPlugin);
    },
  },
  vite: {
    envDir: './../',
    plugins: [
      groupIconVitePlugin({
        customIcon: {
          '.module': 'vscode-icons:file-type-php',
        },
      }),
    ],
    optimizeDeps: {
      exclude: [
        '@nolebase/vitepress-plugin-enhanced-readabilities/client',
        'vitepress',
        '@nolebase/ui',
      ],
    },
    ssr: {
      noExternal: [
        '@nolebase/vitepress-plugin-enhanced-readabilities',
        '@nolebase/ui',
      ],
    },
    server: {
      https: false,
      host: '0.0.0.0',
      port: 5173,
      origin: devServerUrl,
      cors: true,
    },
  },
});
