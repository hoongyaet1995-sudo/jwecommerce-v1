<?php
namespace App\Domain\Marketing;
use App\Models\User;

/** One installation is one merchant catalog; each user's projects remain private. */
final class MarketingPermission
{
 public const VIEW='marketing.view';
 public const EDIT='marketing.edit';
 public const APPROVE_COST='marketing.approve_cost';
 public const ALL=[self::VIEW,self::EDIT,self::APPROVE_COST];
 public static function allows(?User $user,string $permission=self::VIEW):bool{
  if(!$user||!$user->is_admin||!in_array($permission,self::ALL,true))return false;
  if($user->is_super_admin)return true;
  $grants=$user->permissions()->whereIn('permission',self::ALL)->pluck('permission');
  return $grants->contains(self::VIEW)&&$grants->contains($permission);
 }
}
