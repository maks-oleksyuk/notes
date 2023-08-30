import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Notes',
  description: 'Just my notes and code snippets',
  base: '/notes/',
  srcDir: './src',
  outDir: './dist',
  head: [['link', { rel: 'icon', href: 'favicon.ico' }]],
  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
    ],

    socialLinks: [
      { icon: 'github', link: '//github.com/maks-oleksyuk/notes' },
      { icon: 'linkedin', link: '//linkedin.com/in/maks-oleksyuk' }
    ]
  },
})
