<?php
// ============================================================
// STRATEDGE — MISSION CONTROL / Valider les bets
// /panel-x9k3m/valider-bets.php
// Design: Blade Runner 2049 × Bloomberg Terminal × F1 Cockpit
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../includes/tweet-ai.php';
requireAdmin();
$pageActive = 'valider-bets';

$twitterActif = false;
$twitterConfig = [];
$twitterConfigFile = __DIR__ . '/../includes/twitter_keys.php';
if (file_exists($twitterConfigFile)) {
    $twitterConfig = include $twitterConfigFile;
    if (!empty($twitterConfig['actif']) && !empty($twitterConfig['webhook_url'])) {
        require_once __DIR__ . '/../includes/twitter.php';
        $twitterActif = true;
    }
}

$db = getDB();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';
    } elseif (($_POST['action'] ?? '') === 'set_resultat') {
        $betId = (int)($_POST['bet_id'] ?? 0);
        $resultat = $_POST['resultat'] ?? '';
        if ($betId && in_array($resultat, ['gagne', 'perdu', 'annule'])) {
            try {
                $db->prepare("UPDATE bets SET resultat=?, date_resultat=NOW(), actif=0 WHERE id=?")->execute([$resultat, $betId]);
            } catch (Throwable $e) {
                try { $db->prepare("UPDATE bets SET actif=0 WHERE id=?")->execute([$betId]); } catch (Throwable $e2) {}
            }

            $stmt = $db->prepare("SELECT * FROM bets WHERE id=?");
            $stmt->execute([$betId]);
            $bet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($bet) {
                try {
                    $resMap = ['gagne' => 'win', 'perdu' => 'lose', 'annule' => 'void'];
                    $resCode = $resMap[$resultat] ?? $resultat;
                    $titreResult = trim($bet['titre'] ?? '');
                    if ($titreResult !== '' && function_exists('resultatQueueEnqueue')) {
                        resultatQueueEnqueue($db, $titreResult, $resCode);
                        if (function_exists('resultatQueueProcessBatch')) {
                            resultatQueueProcessBatch($db, 90);
                        }
                    }
                } catch (Throwable $e) { error_log('[valider-bets] notif: ' . $e->getMessage()); }

                if ($twitterActif && !empty($twitterConfig['webhook_url']) && $resultat !== 'perdu') {
                    try {
                        $matchName = trim($bet['titre'] ?? '');
                        $coteRaw = (string)($bet['cote'] ?? '');
                        $coteAt = ($coteRaw !== '' && $coteRaw !== '0' && $coteRaw !== '0.00') ? " @ $coteRaw" : '';
                        $isFun = (stripos((string)($bet['categorie'] ?? ''), 'fun') !== false);
                        $msg = $isFun
                            ? ($resultat === 'gagne' ? "🎲 Bet Fun validé{$coteAt} ✅\n\n{$matchName}\n\n📲 stratedgepronos.fr" : "↺ Bet Fun annulé — mise remboursée\n\n{$matchName}\n\n📲 stratedgepronos.fr")
                            : ($resultat === 'gagne' ? "🎾 Bet validé{$coteAt} ✅\n\n{$matchName}\n\n📲 stratedgepronos.fr" : "↺ Bet annulé — mise remboursée\n\n{$matchName}\n\n📲 stratedgepronos.fr");
                        if (function_exists('twitter_post_from_webhook')) {
                            twitter_post_from_webhook($msg, null, $twitterConfig['webhook_url']);
                        }
                    } catch (Throwable $e) { error_log('[valider-bets] tweet: ' . $e->getMessage()); }
                }
            }

            $labels = ['gagne' => 'VALIDATED', 'perdu' => 'LOST', 'annule' => 'VOID'];
            $success = ($labels[$resultat] ?? strtoupper($resultat)) . " · Target #{$betId}";
        }
    }

    $filter = $_POST['filter'] ?? 'en_cours';
    header("Location: ?filter=$filter&msg=" . urlencode($success ?: $error) . "&msg_type=" . ($success ? 'success' : 'error'));
    exit;
}

$filter = $_GET['filter'] ?? 'en_cours';
$msgGet = $_GET['msg'] ?? '';
$msgTypeGet = $_GET['msg_type'] ?? '';

$sql = "SELECT * FROM bets ";
switch ($filter) {
    case 'en_cours': $sql .= "WHERE (resultat IS NULL OR resultat = '' OR resultat = 'en_cours') AND actif = 1 "; break;
    case 'gagne':    $sql .= "WHERE resultat = 'gagne' "; break;
    case 'perdu':    $sql .= "WHERE resultat = 'perdu' "; break;
    case 'annule':   $sql .= "WHERE resultat = 'annule' "; break;
}
$sql .= "ORDER BY date_post DESC LIMIT 80";
$bets = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'en_cours' => (int)$db->query("SELECT COUNT(*) FROM bets WHERE (resultat IS NULL OR resultat = '' OR resultat = 'en_cours') AND actif = 1")->fetchColumn(),
    'gagne'    => (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat = 'gagne'")->fetchColumn(),
    'perdu'    => (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat = 'perdu'")->fetchColumn(),
    'annule'   => (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat = 'annule'")->fetchColumn(),
];
$totalFinished = $counts['gagne'] + $counts['perdu'];
$winRate = $totalFinished > 0 ? round(($counts['gagne'] / $totalFinished) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#05070d">
<title>Mission Control · Validation Bets · StratEdge</title>
<link rel="icon" type="image/png" href="../assets/images/mascotte.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #05070d; --bg-deep: #020308;
    --surface: #0a0d17; --surface-2: #0f1420;
    --border: rgba(255, 45, 120, 0.12);
    --border-bright: rgba(255, 45, 120, 0.35);
    --border-subtle: rgba(255, 255, 255, 0.06);
    --pink: #ff2d78; --pink-glow: rgba(255, 45, 120, 0.5);
    --cyan: #00d4ff; --cyan-glow: rgba(0, 212, 255, 0.5);
    --green: #00e5a0; --green-glow: rgba(0, 229, 160, 0.5);
    --red: #ff3864; --red-glow: rgba(255, 56, 100, 0.5);
    --amber: #ffb547; --amber-glow: rgba(255, 181, 71, 0.5);
    --violet: #b026ff;
    --t1: #ffffff; --t2: #b8c5d6; --t3: #6b7689; --t4: #3a4150;
    --mono: 'JetBrains Mono', monospace;
    --display: 'Orbitron', sans-serif;
    --body: 'Space Grotesk', sans-serif;
    --serif: 'Instrument Serif', serif;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html { overflow-x: hidden; }
  body {
    font-family: var(--body); background: var(--bg); color: var(--t1);
    min-height: 100vh; -webkit-font-smoothing: antialiased;
  }
  /* Les backgrounds ambiants vivent DANS .main pour ne pas recouvrir la sidebar */
  .main { position: relative; padding-bottom: 100px; min-height: 100vh; }
  .main::before {
    content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background:
      radial-gradient(ellipse 800px 600px at 15% 10%, rgba(255, 45, 120, 0.08), transparent 60%),
      radial-gradient(ellipse 700px 500px at 85% 90%, rgba(0, 212, 255, 0.06), transparent 60%),
      radial-gradient(ellipse 400px 400px at 50% 50%, rgba(176, 38, 255, 0.03), transparent 70%);
  }
  .main::after {
    content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: radial-gradient(ellipse at center, black 0%, transparent 85%);
    -webkit-mask-image: radial-gradient(ellipse at center, black 0%, transparent 85%);
  }
  .scanline {
    position: absolute; left: 0; right: 0; height: 2px; z-index: 1;
    background: linear-gradient(90deg, transparent, var(--pink), transparent);
    opacity: 0.3; pointer-events: none; animation: scan 8s linear infinite;
  }
  @keyframes scan { 0% { top: -5%; } 100% { top: 105%; } }

  .page { position: relative; z-index: 2; max-width: 1280px; margin: 0 auto; }

  /* HERO */
  .hero { position: relative; padding: 28px 24px 32px; border-bottom: 1px solid var(--border); }
  .hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
  .hero-identity { flex: 1; }
  .hero-eyebrow {
    display: flex; align-items: center; gap: 8px;
    font-family: var(--mono); font-size: 10px; font-weight: 500;
    letter-spacing: 3px; color: var(--t3); text-transform: uppercase; margin-bottom: 8px;
  }
  .hero-eyebrow .dot {
    width: 6px; height: 6px; border-radius: 50%; background: var(--pink);
    box-shadow: 0 0 10px var(--pink-glow); animation: pulse 2s ease-in-out infinite;
  }
  @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }
  .hero-title {
    font-family: var(--display); font-weight: 900; font-size: clamp(28px, 6vw, 48px);
    line-height: 0.95; letter-spacing: -0.02em; margin-bottom: 6px;
    background: linear-gradient(135deg, #ffffff 0%, #ff8dba 50%, var(--pink) 100%);
    -webkit-background-clip: text; background-clip: text; color: transparent;
  }
  .hero-subtitle {
    font-family: var(--serif); font-style: italic;
    font-size: clamp(14px, 2.5vw, 17px); color: var(--t2); font-weight: 400;
  }
  .hero-back {
    text-decoration: none; color: var(--t3); font-family: var(--mono);
    font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
    padding: 8px 14px; border: 1px solid var(--border); border-radius: 6px;
    transition: all 0.2s; background: rgba(255, 255, 255, 0.02); white-space: nowrap;
  }
  .hero-back:hover { color: var(--cyan); border-color: var(--cyan); background: rgba(0, 212, 255, 0.08); }

  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
  .stat {
    position: relative; padding: 14px 14px 16px;
    background: var(--surface); border: 1px solid var(--border-subtle);
    border-radius: 10px; overflow: hidden; transition: all 0.25s;
  }
  .stat::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, var(--stat-color, var(--pink)), transparent);
    opacity: 0.4;
  }
  .stat:hover { border-color: var(--stat-color, var(--border-bright)); }
  .stat-label {
    font-family: var(--mono); font-size: 9px; font-weight: 500;
    letter-spacing: 2px; color: var(--t3); text-transform: uppercase; margin-bottom: 6px;
  }
  .stat-value {
    font-family: var(--display); font-weight: 700; font-size: clamp(20px, 4vw, 28px);
    color: var(--stat-color, var(--t1)); line-height: 1; font-variant-numeric: tabular-nums;
  }
  .stat-sub { font-family: var(--mono); font-size: 9px; color: var(--t4); margin-top: 4px; letter-spacing: 1px; }
  .stat-pending { --stat-color: var(--amber); }
  .stat-win { --stat-color: var(--green); }
  .stat-loss { --stat-color: var(--red); }
  .stat-rate { --stat-color: var(--cyan); }

  /* TABS */
  .tabs-wrap {
    position: sticky; top: 0; z-index: 20; padding: 14px 24px;
    background: rgba(5, 7, 13, 0.85);
    backdrop-filter: blur(14px) saturate(130%);
    -webkit-backdrop-filter: blur(14px) saturate(130%);
    border-bottom: 1px solid var(--border);
  }
  .tabs { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
  .tabs::-webkit-scrollbar { display: none; }
  .tab {
    flex: 0 0 auto; display: flex; align-items: center; gap: 8px;
    padding: 9px 14px; text-decoration: none;
    background: var(--surface); border: 1px solid var(--border-subtle);
    border-radius: 8px; color: var(--t2);
    font-family: var(--mono); font-size: 11px; font-weight: 500;
    letter-spacing: 2px; text-transform: uppercase;
    transition: all 0.18s; white-space: nowrap;
  }
  .tab:hover { border-color: var(--border-bright); color: var(--t1); }
  .tab.active {
    background: linear-gradient(135deg, rgba(255,45,120,0.18), rgba(255,45,120,0.08));
    border-color: var(--pink); color: var(--pink);
    box-shadow: 0 0 20px rgba(255, 45, 120, 0.25);
  }
  .tab.active[data-key="gagne"] {
    background: linear-gradient(135deg, rgba(0,229,160,0.18), rgba(0,229,160,0.08));
    border-color: var(--green); color: var(--green);
    box-shadow: 0 0 20px rgba(0, 229, 160, 0.2);
  }
  .tab.active[data-key="perdu"] {
    background: linear-gradient(135deg, rgba(255,56,100,0.18), rgba(255,56,100,0.08));
    border-color: var(--red); color: var(--red);
    box-shadow: 0 0 20px rgba(255, 56, 100, 0.2);
  }
  .tab.active[data-key="annule"] {
    background: linear-gradient(135deg, rgba(255,181,71,0.18), rgba(255,181,71,0.08));
    border-color: var(--amber); color: var(--amber);
    box-shadow: 0 0 20px rgba(255, 181, 71, 0.2);
  }
  .tab .tab-count {
    font-family: var(--display); font-weight: 700; font-size: 11px;
    padding: 2px 7px; background: rgba(255, 255, 255, 0.08);
    border-radius: 4px; color: var(--t2);
    min-width: 22px; text-align: center; letter-spacing: 0.5px;
  }
  .tab.active .tab-count {
    background: rgba(0, 0, 0, 0.55);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  /* TOAST */
  .toast {
    position: fixed; top: 90px; left: 50%; transform: translateX(-50%);
    min-width: 280px; max-width: calc(100% - 32px);
    padding: 12px 20px; border-radius: 10px;
    font-family: var(--mono); font-size: 12px; font-weight: 600;
    letter-spacing: 2px; text-transform: uppercase; z-index: 50;
    display: flex; align-items: center; gap: 10px;
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    animation: toast-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2), toast-out 0.4s 3.5s forwards;
  }
  .toast.success {
    background: rgba(0, 229, 160, 0.15); border: 1px solid var(--green);
    color: var(--green); box-shadow: 0 10px 40px rgba(0, 229, 160, 0.2);
  }
  .toast.error { background: rgba(255, 56, 100, 0.15); border: 1px solid var(--red); color: var(--red); }
  @keyframes toast-in { from { opacity: 0; transform: translate(-50%, -20px); } to { opacity: 1; transform: translate(-50%, 0); } }
  @keyframes toast-out { to { opacity: 0; transform: translate(-50%, -10px); } }

  /* FEED */
  .feed { padding: 20px 24px 40px; display: grid; grid-template-columns: 1fr; gap: 14px; }
  @media (min-width: 768px) { .feed { grid-template-columns: repeat(2, 1fr); gap: 16px; } }
  @media (min-width: 1200px) { .feed { grid-template-columns: repeat(3, 1fr); } }

  /* CARD */
  .card {
    position: relative;
    background: linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%);
    border: 1px solid var(--border-subtle); border-radius: 14px; overflow: hidden;
    transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.3, 1), border-color 0.3s, box-shadow 0.3s;
    animation: card-in 0.5s cubic-bezier(0.2, 0.9, 0.3, 1) both;
  }
  @keyframes card-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
  .card:nth-child(1) { animation-delay: 0.02s; }
  .card:nth-child(2) { animation-delay: 0.04s; }
  .card:nth-child(3) { animation-delay: 0.06s; }
  .card:nth-child(4) { animation-delay: 0.08s; }
  .card:nth-child(5) { animation-delay: 0.10s; }
  .card:nth-child(6) { animation-delay: 0.12s; }
  .card:nth-child(n+7) { animation-delay: 0.14s; }
  .card:hover {
    transform: translateY(-2px); border-color: var(--border-bright);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), 0 0 30px rgba(255, 45, 120, 0.06);
  }
  .card::before, .card::after {
    content: ''; position: absolute; width: 14px; height: 14px;
    border-color: var(--pink); z-index: 2; pointer-events: none;
    opacity: 0.5; transition: opacity 0.3s;
  }
  .card::before {
    top: 0; left: 0; border-top: 1px solid; border-left: 1px solid; border-top-left-radius: 14px;
  }
  .card::after {
    bottom: 0; right: 0; border-bottom: 1px solid; border-right: 1px solid; border-bottom-right-radius: 14px;
  }
  .card:hover::before, .card:hover::after { opacity: 1; }

  .card-hero {
    position: relative; height: 140px; overflow: hidden; background: var(--bg-deep);
  }
  .card-hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .card-hero::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(180deg, transparent 0%, rgba(10, 13, 23, 0.2) 40%, rgba(10, 13, 23, 0.98) 100%);
  }
  .card-hero .hero-placeholder {
    display: flex; align-items: center; justify-content: center; height: 100%;
    color: var(--t4); font-size: 3rem; opacity: 0.3;
  }

  .card-ribbon {
    position: absolute; top: 12px; right: 12px; z-index: 3;
    display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;
    max-width: calc(100% - 24px);
  }
  .ribbon-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 9px; border-radius: 5px;
    font-family: var(--mono); font-size: 10px; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
    background: rgba(5, 7, 13, 0.85); border: 1px solid;
  }
  .ribbon-safe { color: var(--pink); border-color: rgba(255, 45, 120, 0.5); }
  .ribbon-live { color: var(--red); border-color: rgba(255, 56, 100, 0.5); animation: live-pulse 1.6s ease-in-out infinite; }
  @keyframes live-pulse { 0%, 100% { box-shadow: 0 0 0 rgba(255, 56, 100, 0.4); } 50% { box-shadow: 0 0 12px rgba(255, 56, 100, 0.6); } }
  .ribbon-combi { color: var(--cyan); border-color: rgba(0, 212, 255, 0.5); }
  .ribbon-fun { color: var(--violet); border-color: rgba(176, 38, 255, 0.5); }
  .ribbon-tennis { color: var(--green); border-color: rgba(0, 229, 160, 0.5); }
  .ribbon-multi { color: var(--cyan); border-color: rgba(0, 212, 255, 0.4); }
  .ribbon-cote {
    color: var(--t1); background: rgba(255, 45, 120, 0.95);
    border-color: var(--pink); font-family: var(--display);
    font-weight: 900; box-shadow: 0 2px 12px rgba(255, 45, 120, 0.4);
  }

  .card-body { position: relative; padding: 16px 18px 14px; }
  .card-id {
    position: absolute; top: -24px; left: 18px; z-index: 3;
    font-family: var(--mono); font-size: 9px; font-weight: 600;
    color: var(--t3); letter-spacing: 2px; text-transform: uppercase;
    background: rgba(5, 7, 13, 0.9); padding: 4px 8px;
    border-radius: 4px; border: 1px solid var(--border-subtle);
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  }
  .card-id::before { content: 'TRGT_'; color: var(--t4); }
  .card-title {
    font-family: var(--body); font-weight: 600; font-size: 15px;
    line-height: 1.35; color: var(--t1); margin-bottom: 10px;
    word-wrap: break-word; overflow-wrap: break-word;
    letter-spacing: -0.01em;
  }
  .card-meta {
    display: flex; gap: 10px; flex-wrap: wrap;
    font-family: var(--mono); font-size: 10px; letter-spacing: 1px;
    color: var(--t3); margin-bottom: 14px; padding-bottom: 14px;
    border-bottom: 1px dashed var(--border-subtle);
  }
  .card-meta > span { margin-right: 10px; }
  .card-meta > span:last-child { margin-right: 0; }
  .card-meta span { display: inline-flex; align-items: center; gap: 4px; }
  .card-meta .meta-dot { color: var(--pink); margin-right: 3px; }
  .card-status {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; margin-bottom: 14px;
    border-radius: 8px; font-family: var(--mono);
    font-size: 11px; font-weight: 600;
    letter-spacing: 1.5px; text-transform: uppercase;
  }
  .card-status-icon { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .card-status.gagne {
    background: rgba(0, 229, 160, 0.08); color: var(--green);
    border: 1px solid rgba(0, 229, 160, 0.3);
  }
  .card-status.gagne .card-status-icon { background: var(--green); box-shadow: 0 0 8px var(--green-glow); }
  .card-status.perdu {
    background: rgba(255, 56, 100, 0.08); color: var(--red);
    border: 1px solid rgba(255, 56, 100, 0.3);
  }
  .card-status.perdu .card-status-icon { background: var(--red); box-shadow: 0 0 8px var(--red-glow); }
  .card-status.annule {
    background: rgba(255, 181, 71, 0.08); color: var(--amber);
    border: 1px solid rgba(255, 181, 71, 0.3);
  }
  .card-status.annule .card-status-icon { background: var(--amber); box-shadow: 0 0 8px var(--amber-glow); }

  /* ACTIONS */
  .card-actions { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 6px; }
  .action-form { margin: 0; }
  .action {
    width: 100%; padding: 14px 10px; border-radius: 10px;
    background: transparent; border: 1px solid; cursor: pointer;
    font-family: var(--mono); font-weight: 700; font-size: 10px;
    letter-spacing: 2px; text-transform: uppercase;
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    transition: all 0.2s cubic-bezier(0.2, 0.9, 0.3, 1);
    position: relative; overflow: hidden;
  }
  .action:active { transform: scale(0.97); }
  .action svg {
    width: 20px; height: 20px; stroke-width: 2; transition: transform 0.3s;
  }
  .action:hover svg { transform: scale(1.15); }
  .action-gagne {
    color: var(--green); border-color: rgba(0, 229, 160, 0.3);
    background: linear-gradient(180deg, rgba(0, 229, 160, 0.04), rgba(0, 229, 160, 0.02));
  }
  .action-gagne:hover {
    border-color: var(--green); color: var(--green);
    background: linear-gradient(180deg, rgba(0, 229, 160, 0.14), rgba(0, 229, 160, 0.18));
    box-shadow: 0 0 25px rgba(0, 229, 160, 0.2), inset 0 0 0 1px rgba(0, 229, 160, 0.3);
  }
  .action-perdu {
    color: var(--red); border-color: rgba(255, 56, 100, 0.25);
    background: linear-gradient(180deg, rgba(255, 56, 100, 0.03), rgba(255, 56, 100, 0.01));
  }
  .action-perdu:hover {
    border-color: var(--red); color: var(--red);
    background: linear-gradient(180deg, rgba(255, 56, 100, 0.12), rgba(255, 56, 100, 0.16));
    box-shadow: 0 0 25px rgba(255, 56, 100, 0.15), inset 0 0 0 1px rgba(255, 56, 100, 0.3);
  }
  .action-annule {
    color: var(--amber); border-color: rgba(255, 181, 71, 0.25);
    background: linear-gradient(180deg, rgba(255, 181, 71, 0.03), rgba(255, 181, 71, 0.01));
  }
  .action-annule:hover {
    border-color: var(--amber); color: var(--amber);
    background: linear-gradient(180deg, rgba(255, 181, 71, 0.12), rgba(255, 181, 71, 0.16));
    box-shadow: 0 0 25px rgba(255, 181, 71, 0.15);
  }
  .action.is-current {
    opacity: 0.5; cursor: default; border-style: dashed;
  }
  .action.is-current:hover { transform: none; box-shadow: none; }
  .action.is-current svg { transform: none; }
  .action.loading { pointer-events: none; opacity: 0.6; }

  /* EMPTY */
  .empty {
    grid-column: 1 / -1; padding: 80px 24px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; position: relative;
  }
  .empty-ring { position: relative; width: 140px; height: 140px; margin-bottom: 32px; }
  .empty-ring svg { width: 100%; height: 100%; animation: spin 20s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .empty-icon {
    position: absolute; inset: 0; display: flex;
    align-items: center; justify-content: center; font-size: 2.2rem;
  }
  .empty-title {
    font-family: var(--display); font-weight: 700; font-size: 22px;
    letter-spacing: 1px; margin-bottom: 10px;
    background: linear-gradient(135deg, var(--t1), var(--green));
    -webkit-background-clip: text; background-clip: text; color: transparent;
  }
  .empty-sub {
    font-family: var(--serif); font-style: italic; font-size: 15px;
    color: var(--t2); max-width: 400px; line-height: 1.5;
  }
  .empty-hint {
    margin-top: 28px; font-family: var(--mono); font-size: 11px;
    letter-spacing: 2px; text-transform: uppercase; color: var(--t3);
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 16px; border: 1px solid var(--border-subtle); border-radius: 6px;
  }

  /* FOOTER */
  .sys-footer {
    padding: 20px 24px; text-align: center;
    font-family: var(--mono); font-size: 9px; letter-spacing: 2px;
    color: var(--t4); text-transform: uppercase;
    border-top: 1px solid var(--border-subtle);
  }
  .sys-footer span { color: var(--pink); }

  /* RESPONSIVE */
  @media (max-width: 600px) {
    .hero { padding: 22px 16px 24px; }
    .hero-top { flex-direction: column; align-items: flex-start; }
    .hero-back { align-self: flex-end; }
    .stats { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .tabs-wrap { padding: 12px 16px; }
    .feed { padding: 16px; gap: 12px; }
    .card-actions { grid-template-columns: 1.2fr 1fr 1fr; gap: 5px; }
    .action { padding: 12px 4px; font-size: 9.5px; }
    .action svg { width: 18px; height: 18px; }
  }
  @media (min-width: 1200px) {
    .hero { padding: 36px 40px 40px; }
    .stats { grid-template-columns: repeat(4, 1fr); gap: 14px; }
    .tabs-wrap { padding: 16px 40px; }
    .feed { padding: 24px 40px 48px; }
  }
</style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">
<div class="scanline"></div>

<div class="page">

  <header class="hero">
    <div class="hero-top">
      <div class="hero-identity">
        <div class="hero-eyebrow">
          <span class="dot"></span>
          <span>MISSION CONTROL · STRATEDGE</span>
        </div>
        <h1 class="hero-title">VALIDATION DES BETS</h1>
        <p class="hero-subtitle">Clôture rapide des paris actifs — <em>set result, notify, publish</em></p>
      </div>
      <a href="/panel-x9k3m/" class="hero-back">← DASHBOARD</a>
    </div>

    <div class="stats">
      <div class="stat stat-pending">
        <div class="stat-label">En attente</div>
        <div class="stat-value"><?= $counts['en_cours'] ?></div>
        <div class="stat-sub">bets actifs</div>
      </div>
      <div class="stat stat-win">
        <div class="stat-label">Gagnés</div>
        <div class="stat-value"><?= $counts['gagne'] ?></div>
        <div class="stat-sub">all time</div>
      </div>
      <div class="stat stat-loss">
        <div class="stat-label">Perdus</div>
        <div class="stat-value"><?= $counts['perdu'] ?></div>
        <div class="stat-sub">all time</div>
      </div>
      <div class="stat stat-rate">
        <div class="stat-label">Win rate</div>
        <div class="stat-value"><?= $winRate ?><span style="font-size:.6em;opacity:.6;margin-left:2px;">%</span></div>
        <div class="stat-sub"><?= $totalFinished ?> finis</div>
      </div>
    </div>
  </header>

  <div class="tabs-wrap">
    <div class="tabs">
      <a href="?filter=en_cours" class="tab <?= $filter === 'en_cours' ? 'active' : '' ?>" data-key="en_cours">
        <span>En cours</span><span class="tab-count"><?= $counts['en_cours'] ?></span>
      </a>
      <a href="?filter=gagne" class="tab <?= $filter === 'gagne' ? 'active' : '' ?>" data-key="gagne">
        <span>Gagnés</span><span class="tab-count"><?= $counts['gagne'] ?></span>
      </a>
      <a href="?filter=perdu" class="tab <?= $filter === 'perdu' ? 'active' : '' ?>" data-key="perdu">
        <span>Perdus</span><span class="tab-count"><?= $counts['perdu'] ?></span>
      </a>
      <a href="?filter=annule" class="tab <?= $filter === 'annule' ? 'active' : '' ?>" data-key="annule">
        <span>Annulés</span><span class="tab-count"><?= $counts['annule'] ?></span>
      </a>
      <a href="?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>" data-key="all">
        <span>Tous</span>
      </a>
    </div>
  </div>

  <?php if ($msgGet): ?>
    <div class="toast <?= htmlspecialchars($msgTypeGet) ?>">
      <?= $msgTypeGet === 'success' ? '▲' : '⊘' ?>
      <?= htmlspecialchars($msgGet) ?>
    </div>
  <?php endif; ?>

  <div class="feed">
  <?php if (empty($bets)): ?>
    <div class="empty">
      <div class="empty-ring">
        <svg viewBox="0 0 140 140" fill="none">
          <circle cx="70" cy="70" r="60" stroke="rgba(0,229,160,0.2)" stroke-width="1" stroke-dasharray="2 6"/>
          <circle cx="70" cy="70" r="50" stroke="rgba(0,229,160,0.3)" stroke-width="1" stroke-dasharray="1 10"/>
          <circle cx="70" cy="70" r="40" stroke="rgba(0,229,160,0.45)" stroke-width="1"/>
        </svg>
        <div class="empty-icon"><?= $filter === 'en_cours' ? '✓' : '⌀' ?></div>
      </div>
      <h2 class="empty-title">
        <?= $filter === 'en_cours' ? 'All clear.' : 'Rien ici.' ?>
      </h2>
      <p class="empty-sub">
        <?= $filter === 'en_cours'
          ? "Aucun bet en attente de validation. Tous les paris actifs sont clôturés. Profite de ta pause."
          : "Aucun bet ne correspond à ce filtre pour le moment." ?>
      </p>
      <div class="empty-hint">◇ System idle · waiting for new bets</div>
    </div>
  <?php else: ?>
    <?php foreach ($bets as $b):
      $type = strtolower($b['type'] ?? 'safe');
      $categorie = strtolower($b['categorie'] ?? 'multi');
      $resultat = $b['resultat'] ?? 'en_cours';
      if (empty($resultat)) $resultat = 'en_cours';

      $ribbonClass = match($type) {
          'live' => 'ribbon-live', 'combi' => 'ribbon-combi',
          'fun' => 'ribbon-fun', default => 'ribbon-safe',
      };
      $ribbonLabel = match($type) {
          'live' => '● LIVE', 'combi' => 'COMBI',
          'fun' => 'FUN', default => 'SAFE',
      };
      $catClass = $categorie === 'tennis' ? 'ribbon-tennis' : 'ribbon-multi';
      $catLabel = $categorie === 'tennis' ? 'TENNIS' : ($categorie === 'fun' ? 'FUN' : 'MULTI');

      // Logique image identique à bets.php (public) :
      // 1) image_path (ou locked_image_path en fallback)
      // 2) Si chemin relatif, on passe par restore-image.php (gère aussi les BLOB)
      // 3) Si URL http(s)://, on garde tel quel
      $rawPath = !empty($b['image_path']) ? $b['image_path'] : ($b['locked_image_path'] ?? '');
      $img = '';
      if (!empty($rawPath)) {
          if (strpos($rawPath, 'http') === 0) {
              $img = $rawPath;
          } else {
              $basename = basename($rawPath);
              $dir = (strpos($rawPath, 'locked') !== false) ? 'locked' : 'bets';
              $img = '/restore-image.php?dir=' . $dir . '&file=' . rawurlencode($basename);
          }
      }
    ?>
    <article class="card">
      <div class="card-hero">
        <?php if ($img): ?>
          <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" onerror="this.parentNode.innerHTML='<div class=&quot;hero-placeholder&quot;>◇</div>'">
        <?php else: ?>
          <div class="hero-placeholder">◇</div>
        <?php endif; ?>
        <div class="card-ribbon">
          <span class="ribbon-chip <?= $ribbonClass ?>"><?= $ribbonLabel ?></span>
          <span class="ribbon-chip <?= $catClass ?>"><?= $catLabel ?></span>
          <?php if (!empty($b['cote']) && $b['cote'] != '0' && $b['cote'] != '0.00'): ?>
            <span class="ribbon-chip ribbon-cote"><?= htmlspecialchars($b['cote']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="card-id"><?= (int)$b['id'] ?></div>
        <h3 class="card-title"><?= htmlspecialchars($b['titre'] ?? '(sans titre)') ?></h3>
        <div class="card-meta">
          <span><span class="meta-dot">◇</span><?= date('d.m.y', strtotime($b['date_post'])) ?></span>
          <span><?= date('H:i', strtotime($b['date_post'])) ?></span>
          <?php if (!empty($b['sport'])): ?>
            <span style="text-transform:uppercase;"><?= htmlspecialchars($b['sport']) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($resultat !== 'en_cours'): ?>
          <div class="card-status <?= $resultat ?>">
            <div class="card-status-icon"></div>
            <span>
              <?= match($resultat) {
                  'gagne' => 'Résultat actuel · Gagné',
                  'perdu' => 'Résultat actuel · Perdu',
                  'annule' => 'Résultat actuel · Annulé',
                  default => ucfirst($resultat)
              } ?>
            </span>
          </div>
        <?php endif; ?>

        <div class="card-actions">
          <form method="POST" class="action-form" data-action="gagne">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="set_resultat">
            <input type="hidden" name="bet_id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="resultat" value="gagne">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="action action-gagne <?= $resultat === 'gagne' ? 'is-current' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>Gagné</span>
            </button>
          </form>
          <form method="POST" class="action-form" data-action="perdu">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="set_resultat">
            <input type="hidden" name="bet_id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="resultat" value="perdu">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="action action-perdu <?= $resultat === 'perdu' ? 'is-current' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              <span>Perdu</span>
            </button>
          </form>
          <form method="POST" class="action-form" data-action="annule">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="set_resultat">
            <input type="hidden" name="bet_id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="resultat" value="annule">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="action action-annule <?= $resultat === 'annule' ? 'is-current' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="1 4 1 10 7 10"></polyline>
                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
              </svg>
              <span>Annulé</span>
            </button>
          </form>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>

  <div class="sys-footer">
    STRATEDGE <span>//</span> MISSION CONTROL <span>//</span> <?= date('Y.m.d · H:i:s') ?>
  </div>

</div>

<script>
  document.querySelectorAll('.action-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      const action = form.dataset.action;
      const btn = form.querySelector('.action');
      const isCurrent = btn.classList.contains('is-current');
      const labels = {
        gagne: { emoji: '✓', label: 'GAGNÉ' },
        perdu: { emoji: '✕', label: 'PERDU' },
        annule: { emoji: '↺', label: 'ANNULÉ' },
      };
      const cfg = labels[action] || { emoji: '?', label: action };
      const msg = isCurrent
        ? `Changer le résultat en ${cfg.emoji} ${cfg.label} ?`
        : `Confirmer : ${cfg.emoji} ${cfg.label} ?`;
      if (!confirm(msg)) { e.preventDefault(); return false; }
      btn.classList.add('loading');
    });
  });

  setTimeout(() => {
    const t = document.querySelector('.toast');
    if (t) t.remove();
  }, 4200);

  // Keyboard shortcuts
  document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    const map = { '1': 'en_cours', '2': 'gagne', '3': 'perdu', '4': 'annule', '5': 'all' };
    if (map[e.key]) window.location = '?filter=' + map[e.key];
  });
</script>

</div><!-- /.main -->

</body>
</html>
