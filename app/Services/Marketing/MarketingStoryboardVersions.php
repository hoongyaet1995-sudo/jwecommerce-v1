<?php

namespace App\Services\Marketing;

use App\Models\MarketingVideoFilm;

/** Resolve version identity without changing a film or its immutable production inputs. */
class MarketingStoryboardVersions
{
    public function filmBinding(MarketingVideoFilm $film, array $snapshot): array
    {
        $root=$film;$seen=[];
        for($i=0;$i<50;$i++){
            if(isset($seen[$root->id]))break;
            $seen[$root->id]=true;
            if(($root->input['version']??'')===MarketingStoryboardProduction::FILM){
                $version=$root->input['story_version']??null;
                $matches=(!isset($root->input['story_id'])||$root->input['story_id']===($snapshot['story_id']??null))&&($snapshot['source_fresh']??false)&&$version===($snapshot['story_version']??null)&&hash_equals($root->input['readiness_digest']??'',hash('sha256',json_encode(app(MarketingVideoSettings::class)->productionScenes($snapshot),JSON_THROW_ON_ERROR)));
                return ['current'=>$matches,'story_version'=>$version,'root_film_id'=>$root->id,'label'=>$matches?'当前工作稿成片':'历史成片'];
            }
            $parent=match($root->input['version']??''){
                MarketingFilmPackaging::VERSION=>$root->input['content_film_id']??null,
                MarketingEditPlanService::VERSION=>$root->input['base_film_id']??null,
                default=>null,
            };
            if(!$parent)break;
            $root=MarketingVideoFilm::where('project_id',$film->project_id)->where('owner_id',$film->owner_id)->find($parent);
            if(!$root)break;
        }
        return ['current'=>false,'story_version'=>null,'root_film_id'=>null,'label'=>'历史成片'];
    }
}
