import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {bundle} from '@remotion/bundler';
import {renderMedia,selectComposition} from '@remotion/renderer';
const [directory,browserExecutable]=process.argv.slice(2);
const serveUrl=await bundle({entryPoint:path.join(path.dirname(fileURLToPath(import.meta.url)),'editing-fixture.tsx'),publicDir:directory});
const composition=await selectComposition({serveUrl,id:'Fixture',browserExecutable});
await renderMedia({serveUrl,composition,browserExecutable,codec:'h264',audioCodec:'aac',pixelFormat:'yuv420p',outputLocation:path.join(directory,'raw.mp4'),concurrency:2,crf:20,logLevel:'warn'});
