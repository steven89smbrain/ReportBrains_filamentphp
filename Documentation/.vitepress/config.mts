import { defineConfig } from 'vitepress'

// GitHub Pages serves a project site from /<repository>/, so the workflow sets
// the base. Locally the site stays at the root.
const base = process.env.DOCS_BASE ?? '/'

export default defineConfig({
    base,
    // These pages document a report syntax that also uses {{ }}. Vue would try to
    // evaluate those as interpolations and fail on expressions such as
    // {{ sum(total) | currency }}, so the templates use different delimiters.
    vue: {
        template: {
            compilerOptions: {
                delimiters: ['{@{@', '@}@}'],
            },
        },
    },
    lang: 'en',
    title: 'Report Designer',
    description: 'Design reports visually in Filament, and export them as PDF, Excel, CSV, HTML or Markdown.',
    cleanUrls: true,
    // Documentation/README.md is the index in the repository; serve it as the home page.
    rewrites: { 'README.md': 'index.md' },
    themeConfig: {
        nav: [
            { text: 'Documentation', link: '/01-installation' },
            { text: 'Changelog', link: '/07-changelog' },
        ],
        sidebar: [
            {
                text: 'Getting started',
                items: [
                    { text: 'Installation', link: '/01-installation' },
                    { text: 'Quick start', link: '/02-quick-start' },
                ],
            },
            {
                text: 'Designing reports',
                items: [
                    { text: 'The visual designer', link: '/10-visual-designer' },
                    { text: 'Document reference', link: '/03-document-reference' },
                    { text: 'Expressions and rendering', link: '/09-expressions-and-rendering' },
                ],
            },
            {
                text: 'Data',
                items: [
                    { text: 'Data sources', link: '/08-data-sources' },
                    { text: 'Loading templates', link: '/05-loading-templates' },
                ],
            },
            {
                text: 'Output',
                items: [
                    { text: 'PDF output', link: '/11-pdf-output' },
                    { text: 'Running reports from code', link: '/12-running-reports' },
                ],
            },
            {
                text: 'Reference',
                items: [
                    { text: 'Configuration', link: '/04-configuration' },
                    { text: 'Architecture', link: '/06-architecture' },
                    { text: 'Changelog', link: '/07-changelog' },
                ],
            },
        ],
        outline: [2, 3],
        search: { provider: 'local' },
        footer: {
            message: 'Commercial licence — one per project.',
            copyright: '© 2026 ReportBrains',
        },
    },
})
