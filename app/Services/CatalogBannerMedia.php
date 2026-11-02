<?php
namespace App\Services;
use App\Models\{Category,Collection};
final class CatalogBannerMedia
{
 public function resolve(Category|Collection|null $entity, string $title): array
 {
  $settings=$entity?->image_settings ?? [];
  return [
   'desktop'=>$entity instanceof Collection ? ($entity->cover_image ?? '') : ($entity?->image_url ?? ''),
   'mobile'=>$settings['mobile'] ?? '',
   'focus'=>in_array($settings['focus']??'', ['left','center','right'],true)?$settings['focus']:'center',
   'focusY'=>in_array($settings['focusY']??'', ['top','middle','bottom'],true)?$settings['focusY']:'middle',
   'alt'=>$settings['alt'] ?? $title,
   'hidden'=>(bool)($settings['hidden']??false),
  ];
 }
}
