import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import {resolve} from 'node:path';
export default defineConfig({root:__dirname,base:'/build-inventory43-preview/',plugins:[react(),tailwindcss()],build:{outDir:resolve(__dirname,'../../public/build-inventory43-preview'),emptyOutDir:true,manifest:'manifest.json',rollupOptions:{input:resolve(__dirname,'src/previews/inventory43/main.tsx')}}});
