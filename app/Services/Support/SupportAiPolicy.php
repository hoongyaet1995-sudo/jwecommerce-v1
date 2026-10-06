<?php
namespace App\Services\Support;
class SupportAiPolicy
{
 public const DEFAULTS = ['storefront'=>true,'facebook'=>true,'instagram'=>true,'whatsapp'=>true,'tiktok'=>false];
 public function enabled(array $settings, ?string $channel): bool
 {
  $channel = match ($channel) { null, 'web_guest', 'web_customer' => 'storefront', default => $channel };
  return ($settings['autoReplyEnabled'] ?? false) === true
   && array_key_exists($channel, self::DEFAULTS)
   && (($settings['aiChannels'][$channel] ?? self::DEFAULTS[$channel]) === true);
 }
}
