import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import {resolve} from 'node:path';
export default defineConfig({root:__dirname,base:'/build-marketplace33-preview/',plugins:[react(),tailwindcss()],
 build:{outDir:resolve(__dirname,'../../public/build-marketplace33-preview'),emptyOutDir:false,manifest:'manifest.json',
 rollupOptions:{input:resolve(__dirname,'src/previews/marketplace33/main.tsx')}}});
