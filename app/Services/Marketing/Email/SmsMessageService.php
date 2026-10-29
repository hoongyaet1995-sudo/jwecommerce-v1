<?php
namespace App\Services\Marketing\Email;
use DOMDocument; use DOMNode; use DOMElement; use InvalidArgumentException;

final class SmsMessageService
{
    public function analyse($message)
    {
        $message = trim((string) $message);
        $characterCount = preg_match_all('/./us', $message, $characters);

        if ($characterCount === false) {
            throw new InvalidArgumentException('The SMS message contains invalid UTF-8 text.');
        }

        $basic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $extension = "\f^{}\\[~]|€";
        $isNormal = true; $units = 0;
        foreach ($characters[0] as $character) {
            if (mb_strpos($basic, $character) !== false) $units++;
            elseif (mb_strpos($extension, $character) !== false) $units += 2;
            else { $isNormal = false; break; }
        }
        if (!$isNormal) $units = strlen(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')) / 2;
        $units = (int) $units;
        $languageType = $isNormal ? '1' : '2';
        $limits = $isNormal ? array(160, 306, 459) : array(70, 134, 201);
        $credits = $this->calculateCredits($units, $limits);
        $currentLimit = $credits === 0
            ? $limits[0]
            : $limits[min($credits - 1, count($limits) - 1)];

        return array(
            'message' => $message,
            'character_count' => $characterCount,
            'encoding_units' => $units,
            'language_type' => $languageType,
            'encoding' => $isNormal ? 'normal' : 'unicode',
            'encoding_label' => $isNormal ? 'Standard SMS' : 'Unicode SMS',
            'estimated_credits' => $credits,
            'current_limit' => $currentLimit,
            'maximum_characters' => $limits[count($limits) - 1],
            'remaining_characters' => max(0, $currentLimit - $units),
            'within_limit' => $units <= $limits[count($limits) - 1],
        );
    }

    private function calculateCredits($characterCount, array $limits)
    {
        if ($characterCount === 0) {
            return 0;
        }

        foreach ($limits as $index => $limit) {
            if ($characterCount <= $limit) {
                return $index + 1;
            }
        }

        $joinedPartLength = $limits[2] - $limits[1];

        return 3 + (int) ceil(($characterCount - $limits[2]) / $joinedPartLength);
    }
}
