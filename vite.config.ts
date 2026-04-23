import { defineConfig } from 'vite'

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/assets',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app: 'resources/ts/main.ts',
        styles: 'resources/css/app.css',
      },
      output: {
        entryFileNames: 'app.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'styles.css') return 'app.css'
          return '[name][extname]'
        },
      },
    },
  },
})

