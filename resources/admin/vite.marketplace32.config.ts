import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import {resolve} from 'node:path';
export default defineConfig({
  root: __dirname,
  plugins: [react(), tailwindcss()],
  server: {host:'127.0.0.1',port:8534,strictPort:true},
  build: {outDir:resolve(__dirname,'../../tmp/marketplace32-build'),emptyOutDir:false,
    rollupOptions:{input:resolve(__dirname,'marketplace32.html')}},
});
