<?php
namespace App\Services\Support;

class SupportReplySegmenter
{
    public function segments(string $answer,array $candidateSegments,int $maximum,int $channelLimit):array
    {
        $answer=trim($answer);$maximum=max(1,min(3,$maximum));
        $valid=count($candidateSegments)>1&&count($candidateSegments)<=$maximum;
        foreach($candidateSegments as $part)$valid=$valid&&is_string($part)&&trim($part)===$part&&$part!==''&&mb_strlen($part)<=$channelLimit;
        // Exact reconstruction preserves every condition and complete URL. Never use rewritten fragments.
        if($valid&&implode("\n\n",$candidateSegments)===$answer)return $candidateSegments;
        if(mb_strlen($answer)>$channelLimit)throw new \LengthException('Reply exceeds channel limit');
        return [$answer];
    }
}
