<?php
declare(strict_types=1);
namespace StratEdgePublic;

/** Public editorial content only. Never queries private Lab analyses on a public route. */
final class Journal
{
    private \PDO $db;
    public function __construct(\PDO $db) { $this->db = $db; }
    public function install(): void
    {
        $sqlite = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $text = $sqlite ? 'TEXT' : 'LONGTEXT';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec("CREATE TABLE IF NOT EXISTS se_public_posts (id VARCHAR(32) PRIMARY KEY, slug VARCHAR(180) NOT NULL UNIQUE, status VARCHAR(20) NOT NULL, publish_at VARCHAR(32) NOT NULL, updated_at VARCHAR(32) NOT NULL, version INTEGER NOT NULL, payload $text NOT NULL)" . $suffix);
        $this->db->exec("CREATE TABLE IF NOT EXISTS se_public_revisions (id VARCHAR(32) PRIMARY KEY, post_id VARCHAR(32) NOT NULL, version INTEGER NOT NULL, created_at VARCHAR(32) NOT NULL, reason VARCHAR(500) NOT NULL, payload $text NOT NULL, UNIQUE(post_id, version))" . $suffix);
        $this->db->exec("CREATE TABLE IF NOT EXISTS se_public_counts (day VARCHAR(10) NOT NULL, source VARCHAR(20) NOT NULL, event VARCHAR(30) NOT NULL, total INTEGER NOT NULL, PRIMARY KEY(day, source, event))" . $suffix);
    }
    public static function now(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
    public static function encode(array $data): string { return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    public static function validate(array $input): array
    {
        $out = [];
        foreach (['title'=>150,'slug'=>180,'summary'=>400,'author'=>100,'body'=>30000,'sources'=>4000,'match_label'=>180,'market'=>180,'odds'=>12,'odds_at'=>32,'kickoff_at'=>32,'score'=>20] as $field=>$limit) {
            $value = $input[$field] ?? '';
            if (!is_scalar($value) || strlen((string)$value) > $limit * 4 || preg_match_all('/./us', (string)$value) === false || preg_match_all('/./us', (string)$value) > $limit) { throw new \InvalidArgumentException('Le champ ' . $field . ' est trop long ou invalide.'); }
            $out[$field] = trim((string)$value);
        }
        if (strlen($out['title']) < 5 || strlen($out['summary']) < 15 || strlen($out['body']) < 40) { throw new \InvalidArgumentException('Ajoutez un titre, un résumé et un texte complet.'); }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $out['slug'])) { throw new \InvalidArgumentException('L’adresse doit contenir uniquement des lettres minuscules, chiffres et tirets.'); }
        foreach (['kind'=>['guide','analyse','debrief','coulisses'],'sport'=>['football','tennis','multisports'],'decision'=>['observation','retenu','ecarte'],'result'=>['en_attente','gagne','perdu','annule']] as $field=>$allowed) {
            $out[$field] = (string)($input[$field] ?? $allowed[0]);
            if (!in_array($out[$field], $allowed, true)) { throw new \InvalidArgumentException('Choix invalide : ' . $field); }
        }
        if ($out['author'] === '') { $out['author'] = 'La rédaction StratEdge'; }
        foreach (['odds_at','kickoff_at'] as $date) {
            if ($out[$date] !== '') {
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $out[$date], new \DateTimeZone('Europe/Paris'));
                if (!$d || $d->format('Y-m-d\TH:i') !== $out[$date]) { throw new \InvalidArgumentException('Date invalide : ' . $date); }
            }
        }
        if ($out['odds'] !== '' && (!is_numeric(str_replace(',', '.', $out['odds'])) || !is_finite((float)str_replace(',', '.', $out['odds'])) || (float)str_replace(',', '.', $out['odds']) <= 1)) { throw new \InvalidArgumentException('La cote doit être finie et supérieure à 1.'); }
        $out['odds'] = str_replace(',', '.', $out['odds']);
        if ($out['decision'] === 'retenu' && ($out['market'] === '' || $out['match_label'] === '' || $out['odds'] === '' || $out['odds_at'] === '' || $out['kickoff_at'] === '')) { throw new \InvalidArgumentException('Une sélection nécessite le match, le marché, la cote, son heure de relevé et le coup d’envoi.'); }
        if ($out['decision'] !== 'retenu') { $out['result'] = 'en_attente'; $out['score'] = ''; }
        foreach (preg_split('/\R/', $out['sources']) as $url) {
            if (trim($url) !== '' && (!filter_var(trim($url), FILTER_VALIDATE_URL) || strtolower((string)parse_url(trim($url), PHP_URL_SCHEME)) !== 'https')) { throw new \InvalidArgumentException('Les sources doivent être des adresses HTTPS, une par ligne.'); }
        }
        return $out;
    }
    private function row(array $row): array
    {
        $row['data'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR); unset($row['payload']); return $row;
    }
    public function recent(bool $admin = false, int $limit = 60): array
    {
        $q = $this->db->prepare('SELECT * FROM se_public_posts' . ($admin ? '' : " WHERE status = 'published' AND publish_at <= ?") . ' ORDER BY publish_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)));
        $q->execute($admin ? [] : [self::now()]); return array_map(fn($r)=>$this->row($r), $q->fetchAll(\PDO::FETCH_ASSOC));
    }
    public function get(string $key, bool $admin = false): ?array
    {
        $q = $this->db->prepare('SELECT * FROM se_public_posts WHERE (id = ? OR slug = ?)' . ($admin ? '' : " AND status = 'published' AND publish_at <= ?"));
        $q->execute($admin ? [$key,$key] : [$key,$key,self::now()]); $row = $q->fetch(\PDO::FETCH_ASSOC); return $row ? $this->row($row) : null;
    }
    public function revisions(string $id): array
    {
        $q = $this->db->prepare('SELECT * FROM se_public_revisions WHERE post_id = ? ORDER BY version DESC');$q->execute([$id]);return array_map(fn($r)=>$this->row($r), $q->fetchAll(\PDO::FETCH_ASSOC));
    }
    public function save(array $input, ?string $id = null, int $expected = 0, string $status = 'draft', ?string $publish = null, string $reason = ''): string
    {
        if (!in_array($status,['draft','published'],true)) { throw new \InvalidArgumentException('Statut invalide.'); }
        $data = self::validate($input); $now = self::now(); $publish = $publish ?: $now;
        if ($status === 'published' && $data['kind'] === 'analyse' && $data['sources'] === '') { throw new \InvalidArgumentException('Ajoutez les sources de cette analyse avant de la publier.'); }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $publish, new \DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d\TH:i:s\Z') !== $publish) { throw new \InvalidArgumentException('Date de publication invalide.'); }
        $this->db->beginTransaction();
        try {
            $old = $id ? $this->get($id, true) : null;
            if ($id && !$old) { throw new \InvalidArgumentException('Publication introuvable.'); }
            if ($old && (int)$old['version'] !== $expected) { throw new \RuntimeException('Cette publication a changé. Rechargez-la avant de modifier.'); }
            $wasPublic = $old && $old['status'] === 'published' && $old['publish_at'] <= $now;
            if ($wasPublic) {
                if ($status !== 'published' || $publish !== $old['publish_at'] || $data['slug'] !== $old['slug']) { throw new \InvalidArgumentException('Une publication parue conserve son adresse, sa date et son accès public.'); }
                if (strlen(trim($reason)) < 8 || strlen($reason) > 500) { throw new \InvalidArgumentException('Expliquez la correction pour les lecteurs (8 à 500 caractères).'); }
                foreach (['decision','match_label','market','odds','odds_at','kickoff_at'] as $field) {
                    if (($old['data'][$field] ?? '') !== $data[$field]) { throw new \InvalidArgumentException('La sélection publiée est figée. Expliquez une rectification dans le texte, sans réécrire le pari initial.'); }
                }
            }
            if ($status === 'published' && !$wasPublic && $data['decision'] === 'retenu') {
                $kickoff = new \DateTimeImmutable($data['kickoff_at'],new \DateTimeZone('Europe/Paris'));
                if ($kickoff->getTimestamp() <= max(time(),$d->getTimestamp())) { throw new \InvalidArgumentException('Une nouvelle sélection doit paraître avant le coup d’envoi.'); }
                if ($data['result'] !== 'en_attente') { throw new \InvalidArgumentException('Une nouvelle sélection doit être en attente de résultat.'); }
            }
            $id = $id ?: bin2hex(random_bytes(16));$version = $old ? (int)$old['version'] + 1 : 1;
            if ($old) {
                $q=$this->db->prepare('UPDATE se_public_posts SET slug=?,status=?,publish_at=?,updated_at=?,version=?,payload=? WHERE id=? AND version=?');
                $q->execute([$data['slug'],$status,$publish,$now,$version,self::encode($data),$id,$expected]);
                if ($q->rowCount() !== 1) { throw new \RuntimeException('Modification simultanée. Rechargez la page.'); }
            } else {
                $this->db->prepare('INSERT INTO se_public_posts (id,slug,status,publish_at,updated_at,version,payload) VALUES (?,?,?,?,?,?,?)')->execute([$id,$data['slug'],$status,$publish,$now,$version,self::encode($data)]);
            }
            // Only published versions enter the public audit trail; draft notes are never exposed.
            if ($status === 'published') {
                $this->db->prepare('INSERT INTO se_public_revisions (id,post_id,version,created_at,reason,payload) VALUES (?,?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$id,$version,$now,$wasPublic ? trim($reason) : 'Publication initiale',self::encode($data)]);
            }
            $this->db->commit();return $id;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }
}
