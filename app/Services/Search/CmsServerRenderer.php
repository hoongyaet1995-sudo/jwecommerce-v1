<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class CmsServerRenderer
{
    public function render(array $payload): string
    {
        $file = public_path('build-ssr/page.cjs');
        abort_unless(is_file($file), 503, 'Public content renderer is unavailable.');
        $input = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $key = 'cms:ssr:'.hash('sha256', hash_file('sha256', $file).$input);

        return Cache::remember($key, 300, function () use ($file, $input) {
            $process = new Process([(string) config('search.node_binary', 'node'), $file], base_path(), null, $input, 20);
            $process->run();
            if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
                Log::warning('cms_server_render_failed', ['exit_code' => $process->getExitCode()]);
                abort(503, 'Public content is temporarily unavailable.');
            }

            return $process->getOutput();
        });
    }
}
