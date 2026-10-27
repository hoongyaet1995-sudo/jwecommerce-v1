<?php

namespace App\Services\Marketing;

use Symfony\Component\Process\Process;

class MarketingMediaTools
{
    /** Bounded diagnostic output, used by silence analysis. Never exposed verbatim to users. */
    public function runCaptured(array $command, int $timeout): array
    {
        if (empty($command[0])) throw new MarketingProductionFailure('media_runtime_not_configured','blocked');
        $process=new Process($command,base_path(),['ELEVENLABS_API_KEY'=>false,'OPENAI_API_KEY'=>false,'EVOLINK_API_KEY'=>false]);
        $process->setTimeout($timeout);$output=['stdout'=>'','stderr'=>''];
        try {$process->mustRun(function($type,$chunk)use(&$output){$key=$type===Process::ERR?'stderr':'stdout';$output[$key].=$chunk;if(strlen($output[$key])>2000000)throw new \RuntimeException('bounded_output');});}
        catch(\Throwable){throw new MarketingProductionFailure('media_processing_failed');}
        return $output;
    }
    public function audioDuration(string $path): float
    {
        $data = $this->probe($path);
        $streams = $data['streams'] ?? [];
        $duration = (float) ($data['format']['duration'] ?? 0);
        if (count($streams) !== 1 || ($streams[0]['codec_type'] ?? '') !== 'audio' || ($streams[0]['codec_name'] ?? '') !== 'mp3' || $duration <= 0 || $duration > 100) {
            throw new MarketingProductionFailure('voice_result_invalid', 'unknown');
        }
        $this->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-xerror', '-i', $path, '-map', '0:a:0', '-c:a', 'pcm_s16le', '-f', 'wav', '-'], 60, true);

        return $duration;
    }

    public function probe(string $path): array
    {
        $output = $this->run([(string) config('marketing.production.ffprobe_binary'), '-v', 'error', '-count_frames', '-show_streams', '-show_format', '-of', 'json', $path], 60);
        try {
            return json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new MarketingProductionFailure('media_validation_failed');
        }
    }

    public function run(array $command, int $timeout, bool $discardOutput = false): string
    {
        if (empty($command[0])) {
            throw new MarketingProductionFailure('media_runtime_not_configured', 'blocked');
        }
        $process = new Process($command, base_path(), ['ELEVENLABS_API_KEY' => false, 'OPENAI_API_KEY' => false, 'EVOLINK_API_KEY' => false]);
        $process->setTimeout($timeout);
        if ($discardOutput) {
            $process->disableOutput();
        }
        try {
            $process->mustRun();
        } catch (\Throwable) {
            // Never put subprocess arguments, environment or supplier responses into merchant errors.
            throw new MarketingProductionFailure('media_processing_failed');
        }

        return $discardOutput ? '' : $process->getOutput();
    }
}
