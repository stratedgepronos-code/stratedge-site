<?php
declare(strict_types=1);
namespace StratEdgePublic;

final class Metrics
{
    /** Aggregate page loads, not unique visitors. No IP, identifier, cookie or full referrer stored. */
    public static function source(array $query = [], string $referrer = ''): string
    {
        $s = is_string($query['utm_source'] ?? null) ? strtolower($query['utm_source']) : '';
        $aliases = ['twitter'=>'x','x'=>'x','telegram'=>'telegram','email'=>'email','newsletter'=>'email','instagram'=>'instagram','youtube'=>'youtube','google'=>'google','direct'=>'direct'];
        if ($s !== '') { return $aliases[$s] ?? 'autre'; }
        $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
        if (in_array($host,['t.co','x.com','twitter.com'],true)) { return 'x'; }
        if ($host === 't.me' || $host === 'telegram.org') { return 'telegram'; }
        if (in_array($host,['www.google.fr','www.google.com','google.fr','google.com'],true)) { return 'google'; }
        return $host === '' || $host === 'stratedgepronos.fr' ? 'direct' : 'autre';
    }
    public static function count(\PDO $db, string $event, string $source = 'direct'): void
    {
        if (!in_array($event,['accueil','journal','article','offres','resultats','methode','choix_offre','inscription'],true)) { return; }
        $source = self::source(['utm_source'=>$source]);
        try {
            $sqlite = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $q = $db->prepare('INSERT INTO se_public_counts (day,source,event,total) VALUES (?,?,?,1) ' . ($sqlite ? 'ON CONFLICT(day,source,event) DO UPDATE SET total=total+1' : 'ON DUPLICATE KEY UPDATE total=total+1'));
            $q->execute([gmdate('Y-m-d'),$source,$event]);
        } catch (\Throwable $e) { /* Measurement must never block reading, registration or checkout. */ }
    }
    public static function report(\PDO $db): array
    {
        $q=$db->prepare('SELECT source,event,SUM(total) AS total FROM se_public_counts WHERE day >= ? GROUP BY source,event');$q->execute([gmdate('Y-m-d',time()-14*86400)]);return $q->fetchAll(\PDO::FETCH_ASSOC);
    }
}
