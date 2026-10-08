<?php
namespace App\Services\Support;
class SupportAiPolicy
{
 public const DEFAULTS = ['storefront'=>true,'facebook'=>true,'instagram'=>true,'whatsapp'=>true,'tiktok'=>false];
 public function enabled(array $settings, ?string $channel): bool
 {
  $channel = match ($channel) { null, 'web_guest', 'web_customer' => 'storefront', default => $channel };
  if (!array_key_exists($channel, self::DEFAULTS)) return false;
  $channels = $this->channels($settings);
  return $channels[$channel] === true;
 }
 public function channels(array $settings): array
 {
  $defaults = ($settings['autoReplyEnabled'] ?? false) === true ? self::DEFAULTS : array_fill_keys(array_keys(self::DEFAULTS), false);
  return array_replace($defaults, $settings['aiChannels'] ?? []);
 }
}
