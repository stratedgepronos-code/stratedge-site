<?php
declare(strict_types=1);
namespace StratEdgeLab;

final class Store
{
    private $db;
    public function __construct(\PDO $db) { $this->db = $db; }

    public function install(): void
    {
        $sqlite = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec('CREATE TABLE IF NOT EXISTS se_lab_runs (id VARCHAR(32) PRIMARY KEY, owner_id INTEGER NOT NULL, kind VARCHAR(20) NOT NULL, created_at VARCHAR(32) NOT NULL, payload ' . ($sqlite ? 'TEXT' : 'LONGTEXT') . ' NOT NULL)' . $suffix);
        $this->db->exec('CREATE TABLE IF NOT EXISTS se_lab_events (id ' . ($sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY') . ', run_id VARCHAR(32) NOT NULL, match_key VARCHAR(64) NOT NULL, owner_id INTEGER NOT NULL, kind VARCHAR(20) NOT NULL, created_at VARCHAR(32) NOT NULL, payload TEXT NOT NULL)' . $suffix);
    }

    public static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function create(string $kind, array $data, int $owner): string
    {
        if (!in_array($kind, ['draft', 'analysis'], true)) { throw new \InvalidArgumentException('Type d’import invalide.'); }
        $id = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare('INSERT INTO se_lab_runs (id, owner_id, kind, created_at, payload) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$id, $owner, $kind, gmdate('c'), self::encode($data)]);
        return $id;
    }

    public function get(string $id, int $owner): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new \InvalidArgumentException('Import introuvable.'); }
        $stmt = $this->db->prepare('SELECT * FROM se_lab_runs WHERE id = ? AND owner_id = ?');
        $stmt->execute([$id, $owner]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { throw new \InvalidArgumentException('Import introuvable.'); }
        $row['data'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['payload']);
        return $row;
    }

    public function recent(int $owner, string $kind = 'analysis', int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare('SELECT id, kind, created_at FROM se_lab_runs WHERE owner_id = ? AND kind = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
        $stmt->execute([$owner, $kind]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function events(string $runId, int $owner): array
    {
        $stmt = $this->db->prepare('SELECT * FROM se_lab_events WHERE run_id = ? AND owner_id = ? ORDER BY id ASC');
        $stmt->execute([$runId, $owner]);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $row['data'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
            unset($row['payload']);
            $out[] = $row;
        }
        return $out;
    }

    public function resultIfAbsent(string $runId, string $key, array $data, int $owner): string
    {
        $this->db->beginTransaction();
        try {
            // Serialize simultaneous imports for this analysis in production.
            $lock = $this->db->prepare('SELECT id FROM se_lab_runs WHERE id = ? AND owner_id = ?' . ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE'));
            $lock->execute([$runId, $owner]);
            if (!$lock->fetchColumn()) { throw new \InvalidArgumentException('Import introuvable.'); }
            $previous = null;
            foreach ($this->events($runId, $owner) as $event) { if ($event['kind'] === 'result' && $event['match_key'] === $key) { $previous = $event['data']; } }
            $status = 'recorded';
            if ($previous !== null) {
                $status = 'existing';
                foreach (['home', 'away', 'period', 'outcome'] as $field) { if (($previous[$field] ?? null) !== $data[$field]) { $status = 'conflict'; } }
            } else { $this->event($runId, $key, 'result', $data, $owner); }
            $this->db->commit();
            return $status;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) { $this->db->rollBack(); } throw $e; }
    }

    public function event(string $runId, string $key, string $kind, array $data, int $owner): void
    {
        if (!in_array($kind, ['context', 'research', 'result'], true)) { throw new \InvalidArgumentException('Événement invalide.'); }
        $run = $this->get($runId, $owner);
        if ($run['kind'] !== 'analysis' || !in_array($key, array_column($run['data']['analysis']['matches'], 'key'), true)) { throw new \InvalidArgumentException('Match introuvable.'); }
        $stmt = $this->db->prepare('INSERT INTO se_lab_events (run_id, match_key, owner_id, kind, created_at, payload) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$runId, $key, $owner, $kind, gmdate('c'), self::encode($data)]);
    }
}
