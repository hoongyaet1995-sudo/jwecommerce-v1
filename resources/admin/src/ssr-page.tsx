import React from 'react';
import {readFileSync} from 'node:fs';
import {renderToString} from 'react-dom/server';
import {PageRuntimeProvider,PageSections} from './cms/PageRuntimeContext';
const payload=JSON.parse(readFileSync(0,'utf8'));
if(!['public','preview'].includes(payload.context?.mode))throw new Error('Only public runtime payloads may use the shared server renderer');
const html=renderToString(<PageRuntimeProvider payload={payload}><PageSections sections={payload.page.document.sections} mode={payload.context.mode}/></PageRuntimeProvider>);
if(!html.trim())throw new Error('No public sections rendered');
process.stdout.write(html);
