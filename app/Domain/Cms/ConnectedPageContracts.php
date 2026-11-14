<?php
namespace App\Domain\Cms;
final class ConnectedPageContracts {
 public static function errors(array $document):array {
  $errors=[];$sections=$document['sections']??[];$metadata=json_decode(file_get_contents(resource_path('admin/src/cms/connectedFamilyMetadata.json')),true);
  foreach($sections as $index=>$section){if(!is_array($section))continue;$path="sections.$index";
   if(isset($section['connectedDiagram'])){$meta=$metadata[$section['connectedDiagram']]??null;if(!$meta||$meta['type']!==($section['type']??null)||$meta['layout']!==($section['connectedLayout']??null))$errors["$path.connectedLayout"]=['Choose a matching shared layout.'];}
   $end=0;foreach($section['titleEmphasis']??[] as $range){if(!is_array($range)||!isset($range['start'],$range['end'])||$range['start']<$end||$range['end']<=$range['start']||$range['end']>(int)(strlen(mb_convert_encoding($section['title']??'','UTF-16LE','UTF-8'))/2)){$errors["$path.titleEmphasis"]=['Use ordered non-overlapping title ranges.'];break;}$end=$range['end'];}
   $matrix=$section['pricingMatrix']??null;if(!is_array($matrix))continue;
   $plans=$section['items']??[];$ids=array_column($plans,'id');if(count($ids)>6||count($ids)!==count(array_unique($ids)))$errors["$path.items"]=['Use at most six plans with unique IDs.'];
   if(!empty($matrix['sourceSectionId'])){$source=null;foreach($sections as $candidate)if(($candidate['id']??null)===$matrix['sourceSectionId'])$source=$candidate;if(!$source||($source['type']??null)!=='pricing_table'||empty($source['pricingMatrix']['enabled'])||!empty($source['pricingMatrix']['sourceSectionId'])||$source['id']===($section['id']??null))$errors["$path.pricingMatrix.sourceSectionId"]=['Choose a source plan section on this page.'];continue;}
   $seen=[];foreach($matrix['groups']??[] as $group){$gid=$group['id']??'';if(!$gid||isset($seen[$gid]))$errors["$path.pricingMatrix"]=['Use unique group and feature IDs.'];$seen[$gid]=true;foreach($group['rows']??[] as $row){$rid=$row['id']??'';if(!$rid||isset($seen[$rid]))$errors["$path.pricingMatrix"]=['Use unique group and feature IDs.'];$seen[$rid]=true;$keys=array_keys((array)($row['cells']??[]));sort($keys);$expected=$ids;sort($expected);if($keys!==$expected)$errors["$path.pricingMatrix"]=['Feature cells must match the current plan IDs.'];}}
  }
  return $errors;
 }
}
