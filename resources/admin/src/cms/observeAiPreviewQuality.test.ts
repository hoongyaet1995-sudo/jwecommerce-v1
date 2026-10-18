// @vitest-environment jsdom
import {afterEach,describe,expect,it,vi} from 'vitest';
import {observeAiPreviewQuality} from './observeAiPreviewQuality';
afterEach(()=>vi.useRealTimers());
describe('AI preview loaded media quality',()=>{
 it('reports delayed image failures, recovery, and removes observers on exit',()=>{
  vi.useFakeTimers();const root=document.createElement('div');root.innerHTML='<div class="cms-render-surface"><img src="/slow-image.png"></div>';
  const image=root.querySelector('img')!;let complete=false,naturalWidth=0;
  Object.defineProperties(image,{complete:{get:()=>complete},naturalWidth:{get:()=>naturalWidth}});
  const report=vi.fn();const stop=observeAiPreviewQuality(root,report);
  vi.advanceTimersByTime(400);expect(report).toHaveBeenLastCalledWith([]);
  complete=true;image.dispatchEvent(new Event('error'));
  expect(report).toHaveBeenLastCalledWith(['1 image(s) failed to load in this preview.']);
  naturalWidth=1200;image.dispatchEvent(new Event('load'));expect(report).toHaveBeenLastCalledWith([]);
  stop();const count=report.mock.calls.length;naturalWidth=0;image.dispatchEvent(new Event('error'));vi.advanceTimersByTime(1000);
  expect(report).toHaveBeenCalledTimes(count);
 });
});
