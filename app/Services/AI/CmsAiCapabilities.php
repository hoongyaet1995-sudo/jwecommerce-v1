<?php
namespace App\Services\AI;
final class CmsAiCapabilities
{
 public function all():array{return json_decode(file_get_contents(resource_path('cms/ai-capabilities.v1.json')),true,512,JSON_THROW_ON_ERROR)['sections'];}
 public function layout(string $type):array{return $this->all()[$type]['aiLayouts']??[];}
 public function surfaces(string $type):array{return $this->all()[$type]['surfaces']??['default'];}
}
