import { viteBundler } from '@vuepress/bundler-vite'
import { searchPlugin } from '@vuepress/plugin-search'
import { shikiPlugin } from '@vuepress/plugin-shiki'
import { defaultTheme } from '@vuepress/theme-default'
import { defineUserConfig } from 'vuepress'

const repo = 'One-Learning-Community/phactorial'
const hostname = 'https://one-learning-community.github.io'
// GitHub Pages serves a project site from /<repo>/. Override with DOCS_BASE for a custom domain.
const base = (process.env.DOCS_BASE ?? '/phactorial/') as `/${string}/`

export default defineUserConfig({
  lang: 'en-US',
  title: 'Phactorial',
  description: 'Math typesetting in plain PHP. Reads TeX and MathML; writes SVG, PNG, MathML, TeX, Word equations and text.',
  base,

  head: [
    ['meta', { name: 'theme-color', content: '#18181b' }],
    ['link', { rel: 'icon', href: `${base}favicon.svg`, type: 'image/svg+xml' }],
    ['link', { rel: 'icon', href: `${base}favicon.ico`, sizes: '32x32' }],
    ['link', { rel: 'apple-touch-icon', href: `${base}favicon-180.png` }],
    ['meta', { property: 'og:image', content: `${hostname}${base}phactorial-social.png` }],
    ['meta', { property: 'og:image:width', content: '1280' }],
    ['meta', { property: 'og:image:height', content: '640' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
  ],

  bundler: viteBundler(),

  theme: defaultTheme({
    hostname,
    logo: '/phactorial-wordmark.svg',
    logoDark: '/phactorial-wordmark-dark.svg',
    logoAlt: 'Phactorial',
    repo,
    docsRepo: repo,
    docsBranch: 'main',
    docsDir: 'site/src',
    editLinkText: 'Edit this page on GitHub',
    contributors: false,
    themePlugins: {
      prismjs: false,
    },

    navbar: [
      { text: 'Overview', link: '/' },
      { text: 'Input', link: '/input.html' },
      { text: 'Output', link: '/output.html' },
      { text: 'Recipes', link: '/recipes.html' },
      { text: 'Backends', link: '/backends.html' },
      { text: 'CommonMark', link: '/commonmark.html' },
      { text: 'Quoin', link: 'https://one-learning-community.github.io/quoin/' },
    ],

    sidebar: [
      {
        text: 'Phactorial',
        children: ['/', '/recipes.md', '/input.md', '/output.md', '/backends.md', '/commonmark.md'],
      },
    ],
  }),

  plugins: [
    shikiPlugin({
      langs: ['php', 'bash', 'json', 'html', 'css'],
      themes: { light: 'github-light', dark: 'github-dark' },
    }),
    searchPlugin({ maxSuggestions: 8 }),
  ],
})
