<?php
/**
 * StratEdge — Enregistrement des visites pour le compteur (dashboard admin).
 * Chaque appel ajoute une ligne dans visiteurs/data/visites.jsonl.
 * Le dashboard admin lit ce fichier pour afficher aujourd'hui / 7j / 30j / all time.
 */

function log_visite(): void {
    $baseDir = defined('PUBLIC_HTML_PATH') ? PUBLIC_HTML_PATH : (__DIR__ . '/..');
    $dataDir = $baseDir . '/visiteurs/data';
    $file    = $dataDir . '/visites.jsonl';

    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    $line = json_encode(['t' => time(), 'u' => $_SERVER['REQUEST_URI'] ?? '/']) . "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
