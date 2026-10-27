import fs from 'node:fs';import {supportedIntervals} from './grounding-core.mjs';
const data=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const result=data.effects.map(effect=>({scene_number:effect.scene_number,intervals:supportedIntervals(effect,data.scenes.find(s=>s.number===effect.scene_number),data.framing.find(f=>f.scene_number===effect.scene_number))}));
process.stdout.write(JSON.stringify(result));
