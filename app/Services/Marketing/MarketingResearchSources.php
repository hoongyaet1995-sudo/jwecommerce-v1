<?php

namespace App\Services\Marketing;

class MarketingResearchSources
{
    public function publicUrl(string $url): ?string
    {
        if (strlen($url) > 2000 || preg_match('/[\x00-\x20\\\\]/', $url)) { return null; }
        $p = parse_url($url);
        if (! $p || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) { return null; }
        $host = strtolower($p['host'] ?? '');
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/', $host) || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/(?:^|\.)(?:localhost|local|internal|lan|home|onion|invalid)$/', $host)) { return null; }
        parse_str($p['query'] ?? '', $query);
        foreach (array_keys($query) as $key) { if (preg_match('/key|token|secret|auth|session|password|signature/i', $key)) { return null; } }
        $query = array_intersect_key($query, array_flip(['v', 'id', 'p', 'page', 'list']));
        foreach ($query as $value) { if (! is_scalar($value) || strlen((string) $value) > 200) { return null; } }
        ksort($query);
        return 'https://'.$host.($p['path'] ?? '/').(count($query) ? '?'.http_build_query($query) : '');
    }

    public function normalize(array $results): array
    {
        $sources = [];
        foreach (array_slice($results, 0, 18) as $item) {
            if (! is_array($item) || ! ($url = $this->publicUrl((string) ($item['url'] ?? '')))) { continue; }
            $failed = in_array($item['_extraction_status'] ?? '', ['failed', 'empty', 'pending'], true);
            $body = ! $failed && is_string($item['raw_content'] ?? null) ? trim(mb_substr(strip_tags($item['raw_content']), 0, 4000)) : '';
            $snippet = is_string($item['content'] ?? null) ? mb_substr(strip_tags($item['content']), 0, 2000) : '';
            preg_match('~https://(?:www\.)?(?:youtube\.com/watch\?v=([\w-]+)|youtu\.be/([\w-]+)|vimeo\.com/(\d+))~i', $body, $videoMatch);
            $linkedVideoId = ! empty($videoMatch[1]) ? 'youtube:'.$videoMatch[1] : (! empty($videoMatch[2]) ? 'youtube:'.$videoMatch[2] : (! empty($videoMatch[3]) ? 'vimeo:'.$videoMatch[3] : null));
            $directVideoId = $this->directVideoIdentity($url);
            $videoId = $directVideoId ?? $linkedVideoId;
            // Generic Transcript headings are eligible only on the actual video URL,
            // with multiple spoken timestamp lines after the heading, not chapter labels.
            $timestamped = false;
            if ($directVideoId && preg_match('/^\h*(?:#{1,6}\h*)?Transcript\h*:?[\h]*\R(.*?)(?=^\h*#{1,6}\h|\z)/imsu', $body, $section)) {
                $timestamped = preg_match_all('/^\h*\[(?:\d{1,2}:)?\d{1,2}:\d{2}\]\h+\S.+$/mu', $section[1]) >= 2;
            }
            $explicit = false;
            if ($videoId && preg_match('/\b(?:video transcript|transcript of (?:the |this )?video)\s*[:\r\n]+(.*)/isu', $body, $section)) {
                $spoken = trim(preg_replace('~https?://\S+~iu', '', $section[1]));
                $explicit = preg_match_all('/\p{L}/u', $spoken) >= 6
                    && ! preg_match('/^(?:loading|(?:transcript|captions|subtitles)\s+(?:unavailable|not available|disabled)|not available|unavailable|error)\b/iu', $spoken);
            }
            $transcript = $timestamped || $explicit;
            $level = $body !== '' ? ($transcript ? 'publisher_transcript_unverified_sync' : 'page_description') : 'search_snippet';
            // A search snippet never replaces content already extracted for this URL.
            if (isset($sources[$url]) && $body === '') { continue; }
            $metrics = [];
            foreach (['views', 'likes', 'ctr', 'retention', 'conversions'] as $name) { $metrics[$name] = ['value' => null, 'unit' => null, 'observed_at' => null, 'origin' => null, 'comparison_context' => null]; }
            $sources[$url] = ['id' => substr(hash('sha256', $url), 0, 24), 'canonical_url' => $url,
                'publisher' => parse_url($url, PHP_URL_HOST), 'title' => mb_substr(strip_tags(is_string($item['title'] ?? null) ? $item['title'] : ''), 0, 250),
                'platform' => in_array(parse_url($url, PHP_URL_HOST), ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be'], true) ? 'YouTube' : 'publisher_web',
                'retrieved_at' => now()->toIso8601String(), 'published_at' => null, 'retrieval_provider' => 'tavily', 'request_id' => $item['_request_id'] ?? null,
                'observation_level' => $level, 'video_identity' => $videoId, 'bounded_excerpt' => $body !== '' ? $body : $snippet, 'content_hash' => hash('sha256', $body !== '' ? $body : $snippet),
                'reference_role' => in_array($item['_role'] ?? '', ['same_industry', 'cross_industry'], true) ? $item['_role'] : 'unknown',
                'extraction_status' => in_array($item['_extraction_status'] ?? '', ['pending', 'content_available', 'failed', 'empty'], true) ? $item['_extraction_status'] : 'not_attempted',
                'extraction_error_code' => in_array($item['_extraction_error'] ?? '', ['content_unavailable', 'empty_content', 'not_returned'], true) ? $item['_extraction_error'] : null,
                'limitations' => $level === 'publisher_transcript_unverified_sync' ? ['Publisher transcript; video synchronization not verified.'] : ['No video playback observed; public text only.'],
                'quality' => ['basis' => $transcript ? 'transcript_text_only' : ($body !== '' ? 'page_text_only' : 'discovery_only'), 'playback_observed' => false, 'performance_verified' => false, 'identity_basis' => $directVideoId ? 'source_url' : ($linkedVideoId ? 'embedded_link_unverified' : 'unknown')],
                'metrics' => $metrics, 'asset_rights' => 'research_only'];
        }
        return array_values($sources);
    }

    private function directVideoIdentity(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $id = $path === '/watch' ? ($query['v'] ?? '') : (preg_match('~^/(?:shorts|embed)/([\w-]{11})/?$~', $path, $m) ? $m[1] : '');
            return is_string($id) && preg_match('/^[\w-]{11}$/', $id) ? 'youtube:'.$id : null;
        }
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true) && preg_match('~^/([\w-]{11})/?$~', $path, $m)) { return 'youtube:'.$m[1]; }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('~^/(\d+)/?$~', $path, $m)) { return 'vimeo:'.$m[1]; }
        return null;
    }

    public function coverage(array $sources): array
    {
        $content = [];
        foreach ($sources as $source) {
            if (($source['observation_level'] ?? '') === 'publisher_transcript_unverified_sync' && ! empty($source['video_identity'])) { $content[$source['video_identity']] ??= $source; }
        }
        $roles = array_count_values(array_column($content, 'reference_role'));
        $status = count($content) >= 4 && ($roles['same_industry'] ?? 0) >= 2 && ($roles['cross_industry'] ?? 0) >= 1 ? 'available' : (count($content) ? 'partial' : 'insufficient');
        return ['status' => $status, 'candidates' => count($sources), 'publisher_transcripts' => count($content), 'video_observations' => 0, 'verified_current_viral' => 0];
    }

    public function analysisSources(array $sources): array
    {
        $groups = ['same_industry' => [], 'cross_industry' => [], 'unknown' => []];
        $seen = [];
        foreach ($sources as $source) {
            if (($source['observation_level'] ?? '') !== 'publisher_transcript_unverified_sync' || empty($source['video_identity']) || isset($seen[$source['video_identity']])) { continue; }
            $seen[$source['video_identity']] = true;
            $groups[$source['reference_role']][] = $source;
        }
        $selected = [];
        for ($i = 0; $i < 6 && count($selected) < 6; $i++) {
            foreach ($groups as $group) { if (isset($group[$i]) && count($selected) < 6) { $selected[] = $group[$i]; } }
        }
        return $selected;
    }
}
