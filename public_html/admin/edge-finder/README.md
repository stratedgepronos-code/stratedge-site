# StratEdge Edge Finder — Admin Dashboard

Dashboard interne pour visualiser et valider les candidats de paris détectés
par le moteur Python `stratedge_edge_finder` (sur PC local).

## Architecture

```
PC local (Python)              GitHub Actions               Hostinger
──────────────────             ────────────────             ─────────
predict_today                                              ┌─ MySQL DB
       │                                                   │  (picks_imports,
       ▼                                                   │   pick_matches,
export_picks.py                                           │   pick_candidates)
       │                                                   │
       ▼                                                   ▼
picks_today.json ──► curl POST + token ──► api/import.php ─┘
                                                           │
                                                           ▼
                                                    /admin/edge-finder
                                                    (dashboard PHP)
```

Le moteur Python tourne en **local** (Python 3.14, scipy/numpy/pandas non-dispo
sur l'hébergement Premium Hostinger). Il génère un JSON quotidien que tu pousses
manuellement vers Hostinger via curl POST authentifié par token.

## Installation initiale (à faire 1 fois)

### 1. Créer la base MySQL Hostinger

Dans hPanel → Bases de données → MySQL :
- Créer une nouvelle DB (ex: `u527192911_edge`)
- Noter le password généré
- Lancer le SQL d'init dans phpMyAdmin (`sql/init_schema.sql` au niveau du repo)

### 2. Configurer config.php sur Hostinger (manuellement, 1 fois)

```bash
# Via SSH ou Gestionnaire de fichiers Hostinger
cd ~/domains/stratedgepronos.fr/public_html/admin/edge-finder/lib/
cp config.example.php config.php
# Éditer config.php avec les vraies valeurs DB + token
```

⚠️ **Le fichier `config.php` est protégé du déploiement** par une exclusion
dans `.github/workflows/deploy-ssh.yml`. Il survit aux push.

### 3. Vérifier la protection .htaccess

Tester :
```
https://stratedgepronos.fr/admin/edge-finder/lib/config.php
```

Doit retourner **403 Forbidden**. Si on voit le code en clair → bug critique.

### 4. Générer le token côté Python

```powershell
python -c "import secrets; print(secrets.token_hex(32))"
```

Mettre cette même valeur :
- Dans `config.php` côté Hostinger (`SE_IMPORT_TOKEN`)
- Dans une variable d'env locale (`STRATEDGE_TOKEN`) côté Python

## Utilisation quotidienne

```powershell
# 1. Sur ton PC : générer le JSON
python scripts\export_picks.py --days 3

# 2. Push vers Hostinger
$env:STRATEDGE_TOKEN = "ton_token_ici"  # ou via .env
curl.exe -X POST `
  -H "Content-Type: application/json" `
  -H "X-StratEdge-Token: $env:STRATEDGE_TOKEN" `
  --data-binary "@export\picks_today.json" `
  https://stratedgepronos.fr/admin/edge-finder/api/import.php

# 3. Consulter le dashboard
# https://stratedgepronos.fr/admin/edge-finder/
```

## Structure des fichiers

```
public_html/admin/edge-finder/
├── api/
│   ├── import.php          ← Reçoit le JSON Python (POST + token)
│   └── decision.php        ← Valider/rejeter un candidat (TODO Phase 4)
├── lib/
│   ├── .htaccess           ← Bloque accès web direct
│   ├── auth.php            ← Vérifie le token X-StratEdge-Token
│   ├── config.php          ← (NOT IN GIT) credentials réels
│   ├── config.example.php  ← Template versionné
│   └── db.php              ← Connexion PDO
├── data/
│   ├── .htaccess           ← Bloque accès web direct
│   ├── imports/            ← Backups JSON (créé auto)
│   └── import.log          ← Log import (créé auto)
└── index.php               ← Dashboard cyberpunk (TODO Phase 4)
```

## Sécurité

- `config.php` jamais committé, jamais accessible en HTTP, jamais écrasé par deploy
- Auth par token timing-safe (`hash_equals`)
- Token = 64 caractères hex (256 bits), inversibles à régénérer
- HTTPS obligatoire (le site est en HTTPS, pas de redirect HTTP→HTTPS à coder ici)
- DB locale Hostinger (pas de port MySQL exposé en externe)
