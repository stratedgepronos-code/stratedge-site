<?php
declare(strict_types=1);
namespace StratEdgeLab;

final class AutoContext
{
    public static function due(array $match, ?array $previous, int $now): bool
    {
        $kickoff = strtotime($match['kickoff']);
        if (!$match['pick'] || $kickoff <= $now || $kickoff > $now + 7 * 86400) { return false; }
        if (!$previous) { return true; }
        $checked = strtotime($previous['checked_at'] ?? '') ?: 0;
        // Retry failures slowly; refresh once official lineups can become available.
        if (($previous['status'] ?? '') === 'error') { return $now - $checked >= 1800; }
        if (($previous['prompt_version'] ?? 0) < 2) { return true; }
        return $kickoff - $now <= 75 * 60 && $now - $checked >= 30 * 60;
    }
}
