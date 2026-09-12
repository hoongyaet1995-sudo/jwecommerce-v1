import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { configDefaults, defineConfig } from 'vitest/config';
import { resolve } from 'node:path';

export default defineConfig({
  root: __dirname,
  base: '/build-admin/',
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': resolve(__dirname, '.'),
    },
  },
  build: {
    outDir: resolve(__dirname, '../../public/build-admin'),
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/main.tsx'),
        'public-page': resolve(__dirname, 'src/public-page.tsx'),
      },
    },
  },
  test: {
    exclude: [...configDefaults.exclude, 'tests/**'],
  },
});
