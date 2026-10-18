import {build} from 'esbuild';
import {fileURLToPath} from 'node:url';
import {resolve,dirname} from 'node:path';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
await build({entryPoints:[resolve(root,'src/ssr-page.tsx')],outfile:resolve(root,'../../public/build-ssr/page.cjs'),platform:'node',target:'node18',format:'cjs',bundle:true,minify:true,jsx:'automatic',loader:{'.css':'empty'},define:{'process.env.NODE_ENV':'"production"'}});
