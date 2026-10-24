# Sauvegardes Live90

Le déploiement d'un nouveau moteur exige une sauvegarde complète avant de remplacer
les fichiers dans `/opt/stratedge/live90`. Une mise à jour d'interface seule ne crée
pas de nouvelle copie de la base.

Depuis le 24 octobre 2026, la sauvegarde est un export SQL compressé directement
pendant la lecture : `/var/backups/stratedge-live90-v4.*/live90.sql.gz`.
Elle ne nécessite plus une copie SQLite temporaire de la taille de la base.
Les anciennes sauvegardes SQLite et leurs archives `.sqlite.gz` restent conservées.

## Garanties et limites

- Connexion source en lecture seule et transaction cohérente en mode WAL : la
  collecte continue pendant l'export. L'instantané correspond au début de lecture,
  pas à la fin de la sauvegarde.
- Toutes les tables utilisateur, lignes, index, vues et triggers sont conservés,
  ainsi que les identifiants accessibles, séquences AUTOINCREMENT et versions SQLite.
  Les statistiques internes de l'optimiseur `sqlite_stat*` sont recalculables et ne
  sont pas archivées. Les tables virtuelles non prises en charge bloquent l'export.
- Mémoire bornée à la taille d'une ligne et aux buffers ; aucune base entière en RAM.
- Marge disque de 2 Gio contrôlée sur les volumes source et destination pendant
  l'opération. Durée limitée à 15 minutes. Toute erreur bloque le déploiement et
  préserve le moteur actuel. Aucune purge de l'historique actif.
- Archive privée (0600), décompression complète et SHA256 contrôlé avant publication
  du manifeste `live90.sql.gz.json`, puis synchronisation sur disque.
- Tests de restauration automatisés : données, schéma, compteurs, types, caractères
  spéciaux, écritures concurrentes, corruption et manque d'espace. Le déploiement
  vérifie l'archive mais ne restaure pas une seconde base complète sur le VPS.
- Sauvegarde logique, pas une copie binaire identique des pages SQLite. Elle reste
  locale au VPS ; elle ne remplace pas une sauvegarde indépendante de ce serveur.
- Aucun changement de forfait d'hébergement et aucune capacité disque physique ajoutée.

## Vérifier une archive

Depuis le dépôt, en remplaçant `DOSSIER` par le répertoire de sauvegarde voulu :

```bash
sudo python3 ops/live90-stream-backup.py verify /var/backups/DOSSIER/live90.sql.gz
```

## Restaurer sans toucher à la production

La cible doit être un **nouveau fichier** sur un volume disposant de la place
nécessaire pour la base restaurée et de 2 Gio de marge. Le programme refuse tout
chemin déjà existant. Utiliser exclusivement une archive de confiance.

```bash
sudo python3 ops/live90-stream-backup.py restore /var/backups/DOSSIER/live90.sql.gz /chemin/nouveau-live90.sqlite
```

La restauration vérifie le SHA256, l'intégrité SQLite et le nombre de lignes de
chaque table. Elle ne remplace jamais la base active, ne redémarre aucun service
et n'envoie aucun message Telegram. Une bascule vers une base restaurée constitue
une opération distincte à préparer, en tenant compte des relevés reçus depuis
l'instantané. Les sauvegardes précédentes ne sont pas supprimées automatiquement.
