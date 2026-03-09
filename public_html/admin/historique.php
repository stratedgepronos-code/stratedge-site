<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageActive = 'historique';

// Bets avec résultat
$bets = $db->query("
    SELECT * FROM bets
    WHERE resultat != 'en_cours'
    ORDER BY COALESCE(date_resultat, date_post) DESC
")->fetchAll();

// Séparer tennis / multi
$betsMulti  = array_filter($bets, fn($b) => ($b['categorie'] ?? 'multi') !== 'tennis');
$betsTennis = array_filter($bets, fn($b) => ($b['categorie'] ?? 'multi') === 'tennis');

// Grouper par mois
function groupParMois(array $bets): array {
    $mois = [];
    foreach ($bets as $b) {
        $date = $b['date_resultat'] ?? $b['date_post'];
        $cle  = date('Y-m', strtotime($date));
        $mois[$cle][] = $b;
    }
    return $mois;
}

$moisMulti  = groupParMois($betsMulti);
$moisTennis = groupParMois($betsTennis);

$moisNoms = [
    '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
    '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août',
    '09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'
];

$resultatConfig = [
    'gagne'  => ['label'=>'✅ Gagné',  'color'=>'#00c864', 'bg'=>'rgba(0,200,100,0.1)',  'border'=>'rgba(0,200,100,0.3)'],
    'perdu'  => ['label'=>'❌ Perdu',  'color'=>'#ff4444', 'bg'=>'rgba(255,68,68,0.1)',   'border'=>'rgba(255,68,68,0.3)'],
    'annule' => ['label'=>'↺ Annulé', 'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,0.1)', 'border'=>'rgba(245,158,11,0.3)'],
];
$typeLabels = ['safe'=>'🛡️ Safe','fun'=>'🎯 Fun','live'=>'⚡ Live','safe,fun'=>'Safe+Fun','safe,live'=>'Safe+Live'];

// Stats globales
function calcStats(array $bets): array {
    $gagnes  = count(array_filter($bets, fn($b) => $b['resultat'] === 'gagne'));
    $perdus  = count(array_filter($bets, fn($b) => $b['resultat'] === 'perdu'));
    $annules = count(array_filter($bets, fn($b) => $b['resultat'] === 'annule'));
    $total   = count($bets);
    $taux    = ($gagnes + $perdus) > 0 ? round($gagnes / ($gagnes + $perdus) * 100) : 0;
    return compact('gagnes','perdus','annules','total','taux');
}

$statsMulti  = calcStats(array_values($betsMulti));
$statsTennis = calcStats(array_values($betsTennis));

// Onglet actif — par défaut ouvrir le mois le plus récent
$onglet = $_GET['onglet'] ?? 'multi';
$moisActifsRaw = $onglet === 'tennis' ? $moisTennis : $moisMulti;
$premierMois = !empty($moisActifsRaw) ? array_key_first($moisActifsRaw) : '';
$moisOuvert = $_GET['mois'] ?? $premierMois;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historique Bets – Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
    *{margin:0;padding:0;box-sizing:border-box;}
    html,body{overflow-x:hidden !important;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;}

    /* Onglets */
    .tabs{display:flex;gap:0;margin-bottom:2rem;background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;overflow:hidden;}
    .tab{flex:1;display:flex;align-items:center;justify-content:center;gap:0.7rem;padding:1rem 1.5rem;text-decoration:none;font-family:'Orbitron',sans-serif;font-size:0.78rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);transition:all 0.2s;border-bottom:3px solid transparent;}
    .tab:first-child{border-right:1px solid var(--border-subtle);}
    .tab.active-multi{color:#00d4ff;border-bottom-color:#00d4ff;background:rgba(0,212,255,0.04);}
    .tab.active-tennis{color:#00d46a;border-bottom-color:#00d46a;background:rgba(0,212,106,0.04);}
    .tab:hover{background:rgba(255,255,255,0.03);}
    .tab-count{font-family:'Space Mono',monospace;font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:10px;}
    .tab-count-multi{background:rgba(0,212,255,0.12);color:#00d4ff;}
    .tab-count-tennis{background:rgba(0,212,106,0.12);color:#00d46a;}

    /* Stats cards */
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
    .sc{border-radius:12px;padding:1.2rem;text-align:center;}
    .sc-val{font-family:'Orbitron',sans-serif;font-size:1.8rem;font-weight:900;}
    .sc-lbl{font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;margin-top:0.3rem;}

    /* Dossiers */
    .dossier{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;margin-bottom:1rem;overflow:hidden;}
    .dossier-header{display:flex;align-items:center;gap:1rem;padding:1.2rem 1.5rem;text-decoration:none;color:inherit;transition:background 0.2s;}
    .dossier-header:hover{background:rgba(255,255,255,0.02);}
    .dossier-header.open{background:rgba(255,45,120,0.04);border-bottom:1px solid var(--border-subtle);}
    .dossier-arrow{color:var(--text-muted);font-size:1.2rem;transition:transform 0.2s;margin-left:auto;}
    .dossier-arrow.open{transform:rotate(90deg);}
    .bet-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;padding:1rem 1.5rem 1.5rem;}
    .bet-card{background:var(--bg-card2);border-radius:12px;overflow:hidden;transition:transform 0.2s;}
    .bet-card:hover{transform:translateY(-3px);}

    /* Catégorie badge dans les cards */
    .cat-badge{display:inline-flex;align-items:center;gap:0.3rem;font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:5px;font-weight:700;margin-bottom:0.4rem;}
    .cat-tennis{background:rgba(0,212,106,0.12);color:#00d46a;border:1px solid rgba(0,212,106,0.3);}
    .cat-multi{background:rgba(0,212,255,0.1);color:#00d4ff;border:1px solid rgba(0,212,255,0.25);}

    @media(max-width:768px){
      .stats-row{grid-template-columns:repeat(2,1fr);gap:0.6rem;}
      .tabs{flex-direction:column;border-radius:10px;}
      .tab{padding:0.75rem 1rem;font-size:0.72rem;border-right:none !important;border-bottom:1px solid var(--border-subtle);}
      .tab:last-child{border-bottom:none;}
      .sc{padding:0.9rem;border-radius:10px;}
      .sc-val{font-size:1.3rem;}
      .sc-lbl{font-size:0.65rem;}
      .dossier{border-radius:10px;margin-bottom:0.8rem;}
      .dossier-header{padding:0.9rem 1rem;gap:0.6rem;}
      .bet-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.6rem;padding:0.7rem;}
    }
    @media(max-width:480px){
      .stats-row{grid-template-columns:1fr 1fr;gap:0.5rem;}
      .sc-val{font-size:1.1rem;}
      .bet-grid{grid-template-columns:1fr 1fr;gap:0.5rem;padding:0.5rem;}
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">

  <div class="page-header" style="margin-bottom:1.5rem;">
    <h1 style="font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:700;">📂 Historique des Bets</h1>
    <p style="color:var(--text-muted);margin-top:0.3rem;">Bets terminés — dissociés par catégorie</p>
  </div>

  <!-- ONGLETS -->
  <div class="tabs">
    <a href="?onglet=multi" class="tab <?= $onglet==='multi' ? 'active-multi' : '' ?>">
      ⚽🏀🏒 Multi-sport
      <span class="tab-count tab-count-multi"><?= $statsMulti['total'] ?></span>
    </a>
    <a href="?onglet=tennis" class="tab <?= $onglet==='tennis' ? 'active-tennis' : '' ?>">
      🎾 Tennis Weekly
      <span class="tab-count tab-count-tennis"><?= $statsTennis['total'] ?></span>
    </a>
  </div>

<?php
// Sélectionner les données selon l'onglet
$statsActives = $onglet === 'tennis' ? $statsTennis : $statsMulti;
$moisActifs   = $onglet === 'tennis' ? $moisTennis  : $moisMulti;
$accentColor  = $onglet === 'tennis' ? '#00d46a' : '#00d4ff';
$accentBg     = $onglet === 'tennis' ? 'rgba(0,212,106,0.05)' : 'rgba(0,212,255,0.05)';
$accentBorder = $onglet === 'tennis' ? 'rgba(0,212,106,0.25)' : 'rgba(0,212,255,0.2)';
?>

  <!-- STATS DE L'ONGLET -->
  <div class="stats-row">
    <div class="sc" style="background:var(--bg-card);border:1px solid var(--border-subtle);">
      <div class="sc-val"><?= $statsActives['total'] ?></div>
      <div class="sc-lbl" style="color:var(--text-muted);">Total bets</div>
    </div>
    <div class="sc" style="background:rgba(0,200,100,0.05);border:1px solid rgba(0,200,100,0.2);">
      <div class="sc-val" style="color:#00c864;"><?= $statsActives['gagnes'] ?></div>
      <div class="sc-lbl" style="color:#00c864;">✅ Gagnés</div>
    </div>
    <div class="sc" style="background:rgba(255,68,68,0.05);border:1px solid rgba(255,68,68,0.2);">
      <div class="sc-val" style="color:#ff4444;"><?= $statsActives['perdus'] ?></div>
      <div class="sc-lbl" style="color:#ff4444;">❌ Perdus</div>
    </div>
    <div class="sc" style="background:<?= $accentBg ?>;border:1px solid <?= $accentBorder ?>;">
      <div class="sc-val" style="color:<?= $accentColor ?>;"><?= $statsActives['taux'] ?>%</div>
      <div class="sc-lbl" style="color:<?= $accentColor ?>;">Taux réussite</div>
    </div>
  </div>

  <?php if (empty($moisActifs)): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:3rem;text-align:center;color:var(--text-muted);">
      <div style="font-size:3rem;margin-bottom:1rem;"><?= $onglet==='tennis' ? '🎾' : '⚽' ?></div>
      <p>Aucun bet terminé dans cette catégorie.</p>
    </div>
  <?php else: ?>

    <?php foreach ($moisActifs as $cleMois => $betsDuMois):
      [$annee, $numMois] = explode('-', $cleMois);
      $nomMois  = $moisNoms[$numMois] . ' ' . $annee;
      $isOpen   = ($cleMois === $moisOuvert);
      $nbG = count(array_filter($betsDuMois, fn($b) => $b['resultat'] === 'gagne'));
      $nbP = count(array_filter($betsDuMois, fn($b) => $b['resultat'] === 'perdu'));
      $nbA = count(array_filter($betsDuMois, fn($b) => $b['resultat'] === 'annule'));
    ?>
    <div class="dossier">
      <a href="?onglet=<?= $onglet ?>&mois=<?= $isOpen ? '' : $cleMois ?>"
         class="dossier-header <?= $isOpen ? 'open' : '' ?>">
        <span style="font-size:1.5rem;"><?= $isOpen ? '📂' : '📁' ?></span>
        <div style="flex:1;">
          <div style="font-family:'Orbitron',sans-serif;font-size:0.95rem;font-weight:700;"><?= $nomMois ?></div>
          <div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem;">
            <?= count($betsDuMois) ?> bet<?= count($betsDuMois)>1?'s':'' ?>
            <?php if($nbG): ?> · <span style="color:#00c864;">✅ <?= $nbG ?></span><?php endif; ?>
            <?php if($nbP): ?> · <span style="color:#ff4444;">❌ <?= $nbP ?></span><?php endif; ?>
            <?php if($nbA): ?> · <span style="color:#f59e0b;">↺ <?= $nbA ?></span><?php endif; ?>
          </div>
        </div>
        <span class="dossier-arrow <?= $isOpen ? 'open' : '' ?>">›</span>
      </a>

      <?php if ($isOpen): ?>
      <div class="bet-grid">
        <?php foreach ($betsDuMois as $b):
          $rc = $resultatConfig[$b['resultat']];
          $imgSrc = !empty($b['image_path']) ? betImageUrl($b['image_path']) : '';
          $dateR  = $b['date_resultat'] ? date('d/m/Y', strtotime($b['date_resultat'])) : date('d/m/Y', strtotime($b['date_post']));
          $types  = explode(',', $b['type']);
          $isTennis = ($b['categorie'] ?? 'multi') === 'tennis';
        ?>
        <div class="bet-card" style="border:1px solid <?= $rc['border'] ?>;">
          <?php if ($imgSrc): ?>
            <div style="position:relative;cursor:zoom-in;" onclick="openLightbox('<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>')">
              <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" style="width:100%;height:140px;object-fit:cover;display:block;" alt="" loading="lazy" onerror="this.onerror=null;this.style.display='none';">
              <div style="position:absolute;inset:0;background:<?= $rc['bg'] ?>;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:2.5rem;filter:drop-shadow(0 0 8px <?= $rc['color'] ?>);">
                  <?= ['gagne'=>'✅','perdu'=>'❌','annule'=>'↺'][$b['resultat']] ?>
                </span>
              </div>
              <div style="position:absolute;bottom:5px;right:5px;background:rgba(0,0,0,0.7);color:white;font-size:0.65rem;padding:0.2rem 0.4rem;border-radius:4px;">🔍 Zoom</div>
            </div>
          <?php else: ?>
            <div style="width:100%;height:80px;background:<?= $rc['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:2rem;">
              <?= ['gagne'=>'✅','perdu'=>'❌','annule'=>'↺'][$b['resultat']] ?>
            </div>
          <?php endif; ?>
          <div style="padding:0.8rem;">
            <!-- Badge catégorie -->
            <div class="cat-badge <?= $isTennis ? 'cat-tennis' : 'cat-multi' ?>">
              <?= $isTennis ? '🎾 Tennis' : '⚽ Multi' ?>
            </div>
            <!-- Type badges -->
            <div style="display:flex;gap:0.3rem;flex-wrap:wrap;margin-bottom:0.5rem;">
              <?php foreach ($types as $t): $t=trim($t); ?>
                <span style="font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:5px;background:rgba(255,255,255,0.05);color:var(--text-muted);">
                  <?= $typeLabels[$t] ?? $t ?>
                </span>
              <?php endforeach; ?>
            </div>
            <?php if ($b['titre']): ?>
              <div style="font-size:0.82rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($b['titre']) ?>">
                <?= clean($b['titre']) ?>
              </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.5rem;">
              <span style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;border:1px solid <?= $rc['border'] ?>;padding:0.2rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:700;">
                <?= $rc['label'] ?>
              </span>
              <span style="font-size:0.72rem;color:var(--text-muted);"><?= $dateR ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<!-- LIGHTBOX -->
<div id="lightbox" onclick="closeLightbox()"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(5,8,16,0.97);align-items:center;justify-content:center;padding:1.5rem;">
  <button onclick="closeLightbox()" style="position:fixed;top:1rem;right:1rem;background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.3);color:white;width:40px;height:40px;border-radius:50%;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
  <img id="lightboxImg" src="" style="max-width:95vw;max-height:90vh;border-radius:10px;box-shadow:0 0 40px rgba(255,45,120,0.2);">
</div>
<script>
function openLightbox(src){const lb=document.getElementById('lightbox');document.getElementById('lightboxImg').src=src;lb.style.display='flex';document.body.style.overflow='hidden';}
function closeLightbox(){document.getElementById('lightbox').style.display='none';document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeLightbox();});
</script>
</body>
</html>
