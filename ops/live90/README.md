# Installation StratEdge du ZIP SEUIL90 v1

Les fichiers PHP sont versionnés sous public_html. Le programme fourni est conservé sous ops/live90 ; le script install.sh a été adapté au déploiement Git (pas de recopie des fichiers publics). Les tests pointent vers ces fichiers déployés.

Après déploiement Git, exécuter sur le VPS :

```sh
sudo bash ops/live90/install.sh /var/www/stratedgepronos.fr/public_html
sudo systemctl enable --now seuil90-live.service
```

Le script refuse une installation existante. Il initialise exclusivement /var/lib/stratedge/live90.sqlite et ne modifie pas l’environnement partagé ni l’ancien service. Le service force SE90_TELEGRAM=0 : aucun message envoyé lors de cette installation. Une activation Telegram ultérieure nécessite une décision explicite et une modification du service.

Accès super-administrateur : /panel-x9k3m/slate/live90/. Dans le navigateur, installer le collecteur fourni avec Tampermonkey, puis renseigner localement le token existant via son menu. Ne pas publier ce token. Importer le profil avant le coup d’envoi. Les données PackBall des deux équipes ne constituent pas automatiquement des bilans par lieu.

Validation : tests Python, parseurs Node et syntaxe PHP. Le flux réel dépend de l’onglet PackBall et sera vérifiable seulement après configuration du navigateur.

Retour arrière : arrêter/désactiver uniquement seuil90-live.service ; conserver sa base pour préserver l’historique. Ne pas supprimer ni écraser les données des autres modules.
