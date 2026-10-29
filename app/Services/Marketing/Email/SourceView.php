<?php
namespace App\Services\Marketing\Email;
final class SourceView {
 public static function url(string $path):string {
  $root='/admin/marketing/campaigns/templates';
  if($path==='templates')return url($root);
  if($path==='templates/create/email/editor')return url($root.'/email/editor');
  if(str_starts_with($path,'templates/create/email/editor/starter/'))return url($root.'/email/editor').'?starter='.basename($path);
  if(str_starts_with($path,'assets/libs/bootstrap-icons'))return asset('marketing-studio/bootstrap-icons.css');
  if(str_starts_with($path,'assets/images/logo_icon'))return asset('marketing-studio/crm-email/jw.svg');
  if($path==='assets')return url('/admin/marketing/content');
  return asset('marketing-studio/crm-email/'.preg_replace('/^assets\//','',$path));
 }
}
