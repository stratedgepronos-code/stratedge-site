# ⚙️ STRATEDGE ODDS ENGINE v1 — la fondation CLV

## Pourquoi
Tout edge durable repose sur la même infrastructure : capturer les cotes
(ouverture → mouvements → clôture), les historiser, les deviger. L'engine
alimente : le CLV automatique des picks, le futur Boost Scanner (P2),
le Lag Detector (P3) et les backtests honnêtes.

## Architecture
```
cron (5-10 min) → ingest.php → API cotes → oe_events / oe_odds (deltas only)
                              → capture clôture (T-10 min avant kickoff)
clv-compute.php → devig power de la clôture ref (pinnacle) → oe_clv
```
- **Deltas uniquement** : une ligne oe_odds par CHANGEMENT de cote, pas par
  poll → historique complet des mouvements, stockage minimal.
- **Multi-provider** : driver The Odds API v4 implémenté ; OddsPapi
  (350+ books dont Pinnacle/Singbet/Stake, historique gratuit) = prochain
  driver dans oeFetchOdds().

## Installation (VPS)
```bash
mysql -u <user> -p <db> < sql/migration_odds_engine.sql
cd public_html/admin/odds-engine
cp config.local.example.php config.local.php   # + clé API
php ingest.php            # premier run manuel
crontab -e :
*/7 8-23 * * * php /var/www/stratedgepronos.fr/public_html/admin/odds-engine/ingest.php >> /dev/null 2>&1
```

## Choix API (état juillet 2026)
| Provider | Books | Sharp ref | Prix |
|---|---|---|---|
| The Odds API | ~40 dont Betclic/Unibet (EU) | Pinnacle (EU) | crédits, entrée ~$30-59/mois |
| OddsPapi | 350+ dont Pinnacle/Singbet/Stake/exchanges | oui | per-request, free tier + historique gratuit |
Recommandation : démarrer The Odds API (schéma stable), migrer/doubler avec
OddsPapi quand P3 (lag detector) exigera plus de books et du live.

## CLV d'un pick
```bash
php clv-compute.php --bet=507 --event=123 --market=h2h --selection="Karlsruher SC"
# → Bet #507 | prise 2.80 | clôture pinnacle 2.55 | fair 2.62 | CLV +6.87%
```
Objectif méthodologique (tous syndicates) : CLV moyen > 0 sur 50 picks
glissants ; CLV < 0 sur 30 picks = gate NO-GO et audit.

## Roadmap branchée dessus
- **P2 Boost Scanner FR** : scraper boosts Winamax/Betclic/Unibet → devig
  vs consensus oe_odds → alerte VRAI/FAUX BOOST (Telegram + page membre)
- **P3 Lag Detector** : trigger sur mouvement ref_book > seuil → scan
  fr_books pas encore alignés → alerte cote périmée
- **P4 Live** : brancher les alertes tennis/CS2 existantes sur le flux
