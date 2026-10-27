<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
use App\Services\Marketing\Contracts\MarketingToolAdapter;
/** Sealed adapter: only existing owned Remotion/FFmpeg templates, never AI-supplied commands. */
class MarketingLocalFilmToolAdapter implements MarketingToolAdapter {
 public const VERSION='owned-local-film-adapter-v1';
 public function execute(array $task):array{
  abort_unless(($task['version']??'')===MarketingToolTasks::VERSION&&($task['subject']['type']??'')==='film',422,'本地成片适配器不接受此任务类型。');$s=$task['subject'];$film=MarketingVideoFilm::where('project_id',$s['project_id'])->where('owner_id',$s['owner_id'])->findOrFail($s['id']);
  $digest=MarketingEditingDigest::hash(['film_id'=>$film->id,'fingerprint'=>$film->fingerprint,'input'=>$film->input]);abort_unless(hash_equals($task['source_digest']??'',$digest),409,'工具任务来源已变化。');return app(MarketingVideoCompositor::class)->film($film);
 }
 public function verify(array $output):void{
  abort_unless(is_string($output['path']??null)&&is_string($output['sha256']??null)&&preg_match('/^[a-f0-9]{64}$/D',$output['sha256']),409,'本地工具输出缺少有效校验信息。');
  app(MarketingVideoFiles::class)->file($output['path'],$output['sha256']);
 }
}
