import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({plugins:[react()],define:{'process.env.NODE_ENV':'"production"'},build:{outDir:'../../public/marketing-studio/packaging',emptyOutDir:true,lib:{entry:'main.jsx',formats:['es'],fileName:()=> 'packaging.js',cssFileName:'packaging'},rollupOptions:{output:{assetFileNames:'[name][extname]'}}}});
