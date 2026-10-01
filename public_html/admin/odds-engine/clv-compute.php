<?php
// STRATEDGE ODDS ENGINE — calcul du CLV d'un bet
// Usage : php clv-compute.php --bet=507 --event=123 --market=h2h --selection="Paris Saint Germain" [--line=2.5]
if (php_sapi_name() !== 'cli') die("CLI uniquement\n");
require __DIR__ . '/lib/odds-lib.php';

$o = getopt('', ['bet:', 'event:', 'market:', 'selection:', 'line::']);
if (empty($o['bet']) || empty($o['event']) || empty($o['market']) || empty($o['selection']))
    die("params requis: --bet --event --market --selection [--line]\n");

$db  = oeDb();
$cfg = oeConfig();
$line = isset($o['line']) && $o['line'] !== '' ? (float)$o['line'] : null;

$bet = $db->prepare("SELECT id, cote FROM bets WHERE id=?");
$bet->execute([(int)$o['bet']]);
$bet = $bet->fetch(PDO::FETCH_ASSOC);
if (!$bet) die("bet introuvable\n");
$odds_taken = (float)str_replace(',', '.', (string)$bet['cote']);

// Clotures du book de reference sur ce marche (toutes les issues pour deviger)
$q = $db->prepare(
    "SELECT selection, odds FROM oe_odds
     WHERE event_id=? AND book=? AND market=? AND (line <=> ?) AND is_closing=1");
$q->execute([(int)$o['event'], $cfg['ref_book'], $o['market'], $line]);
$rows = $q->fetchAll(PDO::FETCH_KEY_PAIR);
if (!$rows) die("pas de cloture " . $cfg['ref_book'] . " pour cet event/marche\n");

$sels = array_keys($rows);
$odds = array_values(array_map('floatval', $rows));
$fair = oeDevigPower($odds);
$idx  = array_search($o['selection'], $sels, true);
if ($idx === false) die("selection introuvable dans les clotures: " . implode(' | ', $sels) . "\n");

$closing_odds = $odds[$idx];
$closing_fair = $fair[$idx] > 0 ? round(1 / $fair[$idx], 3) : null;
$clv = $closing_fair ? round(($odds_taken / $closing_fair - 1) * 100, 2) : null;

$db->prepare(
    "INSERT INTO oe_clv (bet_id, event_id, market, selection, line, odds_taken, closing_odds, closing_fair, clv_pct, ref_book, computed_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE closing_odds=VALUES(closing_odds), closing_fair=VALUES(closing_fair),
       clv_pct=VALUES(clv_pct), computed_at=UTC_TIMESTAMP()")
   ->execute([(int)$o['bet'], (int)$o['event'], $o['market'], $o['selection'], $line,
              $odds_taken, $closing_odds, $closing_fair, $clv, $cfg['ref_book']]);

printf("Bet #%d | prise %.2f | cloture %s %.2f | fair %.2f | CLV %+.2f%%\n",
    $bet['id'], $odds_taken, $cfg['ref_book'], $closing_odds, $closing_fair, $clv);
