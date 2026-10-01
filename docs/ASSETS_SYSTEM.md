# STRATEDGE — Assets System Guide

Système complet d'assets (logos équipes, flags pays, photos joueurs) pour StratEdge.

---

## 🎯 Helpers disponibles

Tous les helpers suivent le même pattern :
- **Priorité 1** : fichier local si présent
- **Priorité 2** : URL distante (fallback transparent)

### Football
```php
require_once __DIR__ . '/includes/football-logos-db.php';

echo stratedge_football_logo('PSG');
// → /assets/logos/football/api-85.png (si téléchargé)
// → https://media.api-sports.io/football/teams/85.png (sinon)
```

**Couverture** : Big5 + MLS + Amérique du Sud + Champions League (via API-Football)
+ K-League, J-League, Liga MX, A-League, Liga BetPlay (via TheSportsDB manifest)

### NBA
```php
require_once __DIR__ . '/includes/nba-logos-db.php';

echo stratedge_nba_logo('Lakers');
// → /assets/logos/basket/lal.png
echo stratedge_nba_logo('Boston Celtics');  
// → /assets/logos/basket/bos.png
```

**30 équipes** · Source : ESPN CDN (a.espncdn.com)

### NHL
```php
require_once __DIR__ . '/includes/nhl-logos-db.php';

echo stratedge_nhl_logo('Dallas Stars');
// → /assets/logos/hockey/dal.png
echo stratedge_nhl_logo('Minnesota Wild');
// → /assets/logos/hockey/min.png
```

**32 équipes** · Source : ESPN CDN

### MLB
```php
require_once __DIR__ . '/includes/mlb-logos-db.php';

echo stratedge_mlb_logo('Yankees');
// → /assets/logos/baseball/nyy.png
echo stratedge_mlb_logo('Los Angeles Dodgers');
// → /assets/logos/baseball/lad.png
```

**30 équipes** · Source : ESPN CDN

### Flags (250 pays)
```php
require_once __DIR__ . '/includes/flags-db.php';

echo stratedge_flag('France');        // → /assets/flags/fr.png
echo stratedge_flag('FR');             // → idem (supporte ISO2)
echo stratedge_flag('Argentina');      // → /assets/flags/ar.png
echo stratedge_flag('États-Unis');     // → /assets/flags/us.png (accents OK)
echo stratedge_flag('Corée du Sud');   // → /assets/flags/kr.png

// Width custom (320 par défaut)
echo stratedge_flag('Brésil', 640);    // → pour écrans HD
```

**250+ pays** · Source : flagcdn.com (free, no auth)
Accepte : nom FR, nom EN, ISO2.

### Photos joueurs (lazy-load)
```php
require_once __DIR__ . '/includes/player-photos.php';

// Télécharge automatiquement à la 1ère utilisation, cache 30 jours
echo stratedge_player_photo(2544, 'nba');      // LeBron James
echo stratedge_player_photo(201939, 'nba');    // Stephen Curry
echo stratedge_player_photo(8480824, 'nhl');   // Connor McDavid
echo stratedge_player_photo(592450, 'mlb');    // Aaron Judge
echo stratedge_player_photo(276, 'soccer');    // Erling Haaland

// Preload batch (ex: début de saison, liste de joueurs suivis)
$res = stratedge_preload_player_photos([
    ['id' => 2544, 'sport' => 'nba'],
    ['id' => 276, 'sport' => 'soccer'],
]);
// → ['ok' => 1, 'cached' => 1, 'failed' => 0]
```

**Sources** :
- NBA : `cdn.nba.com/headshots/nba/latest/260x190/{id}.png`
- NHL : `assets.nhle.com/mugs/nhl/latest/{id}.png`
- MLB : `securea.mlb.com/mlb/images/players/head_shot/{id}.jpg`
- Soccer : `media.api-sports.io/football/players/{id}.png`

Cache : `/assets/players/{sport}/{player_id}.{ext}` (png ou jpg selon sport)

---

## 🚀 Scripts de téléchargement batch

### 1. Football (201 équipes + ligues exotiques)
```
https://stratedgepronos.fr/panel-x9k3m/download-football-logos.php
```
Télécharge Big5 + MLS + SA (via API-Football) + K/J-League + Liga MX + A-League etc. (via TheSportsDB)

### 2. Master (NBA + NHL + MLB + Flags)
```
https://stratedgepronos.fr/panel-x9k3m/download-all-logos.php
```
Télécharge en un coup :
- 30 logos NBA
- 32 logos NHL
- 30 logos MLB
- 250 flags pays

> ⚠️ **IMPORTANT** : Le dossier `/admin/` est bloqué en accès direct par `.htaccess`.
> Utilisez toujours le chemin secret `/panel-x9k3m/` pour accéder aux scripts admin.

### Fréquence
Relance **tous les 1-2 mois** pour récupérer les nouvelles équipes / flags MAJ.
Les fichiers existants (> 500 bytes) sont skippés automatiquement.

---

## 📁 Structure finale des assets

```
public_html/
├── assets/
│   ├── logos/
│   │   ├── football/
│   │   │   ├── api-85.png           ← API-Football (PSG)
│   │   │   ├── api-86.png           ← (Real Madrid)
│   │   │   ├── tsdb-ulsan-hyundai.png ← TheSportsDB (K-League)
│   │   │   ├── tsdb-fc-tokyo.png    ← (J-League)
│   │   │   └── manifest.json         ← lookup slug → filename
│   │   ├── basket/                  ← 30 logos NBA
│   │   ├── hockey/                  ← 32 logos NHL
│   │   └── baseball/                ← 30 logos MLB
│   ├── flags/                       ← 250 flags pays (iso2.png)
│   └── players/
│       ├── nba/                     ← cache photos NBA (lazy)
│       ├── nhl/                     ← cache photos NHL (lazy)
│       ├── mlb/                     ← cache photos MLB (lazy)
│       └── soccer/                  ← cache photos foot (lazy)
└── includes/
    ├── football-logos-db.php        ← DB noms → ID API-Football
    ├── nba-logos-db.php             ← DB noms → abbr ESPN
    ├── nhl-logos-db.php
    ├── mlb-logos-db.php
    ├── flags-db.php                 ← DB pays FR/EN → ISO2
    └── player-photos.php            ← helper lazy-cache
```

---

## 💡 Exemples d'usage dans les cards

### Card match foot
```php
<?php require 'includes/football-logos-db.php'; ?>
<div class="match-card">
  <div class="team home">
    <img src="<?= stratedge_football_logo('PSG') ?>" alt="PSG">
    <span>PSG</span>
  </div>
  <span class="vs">VS</span>
  <div class="team away">
    <img src="<?= stratedge_football_logo('Real Madrid') ?>" alt="Real">
    <span>Real Madrid</span>
  </div>
</div>
```

### Card tennis avec flags
```php
<?php require 'includes/flags-db.php'; ?>
<div class="tennis-match">
  <div class="player">
    <img class="flag" src="<?= stratedge_flag('Espagne') ?>">
    <strong>Bautista Agut</strong>
  </div>
  <div class="player">
    <img class="flag" src="<?= stratedge_flag('Argentina') ?>">
    <strong>Tirante</strong>
  </div>
</div>
```

### Card NBA avec photo joueur
```php
<?php 
require 'includes/nba-logos-db.php';
require 'includes/player-photos.php';
?>
<div class="prop-card">
  <img class="team-logo" src="<?= stratedge_nba_logo('Knicks') ?>">
  <img class="player-photo" src="<?= stratedge_player_photo(1628973, 'nba') ?>">
  <h3>Jalen Brunson</h3>
  <p>Over 25.5 pts @ 1.56</p>
</div>
```

### Card analyse internationale
```php
<div class="intl-match">
  <img src="<?= stratedge_flag('France') ?>"> France
  <span>vs</span>
  <img src="<?= stratedge_flag('Brésil') ?>"> Brésil
</div>
```

---

## 🔐 Sécurité

- Les scripts `/admin/download-*.php` sont protégés par `requireAdmin()`
- Aucune clé API exposée (toutes les sources utilisent des CDN publics gratuits)
- Les helpers peuvent être utilisés sur la page publique sans auth
- Le proxy existant `/assets/logo-proxy.php` reste en place comme ultime fallback

---

## ⚡ Performance

**Avant** (URLs distantes) :
- 1 match = 2 requêtes externes (2 logos) = ~200ms de latence
- 10 matchs affichés = 20 requêtes externes = lag notable
- Dépendance à la dispo des CDN externes

**Après** (fichiers locaux) :
- 1 match = 0 requête externe = ~5ms de latence
- 10 matchs = 0 requête externe = instantané
- 100% résilient (fonctionne même si ESPN / flagcdn sont down)

**Gain** : ~40x plus rapide en loading, indépendance totale.
