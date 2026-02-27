<?php
// ============================================================
// STRATEDGE — Coffre-Fort : Configuration
// ⚠️  FICHIER ULTRA-SENSIBLE — ne JAMAIS partager / git push
// ⚠️  Ajouter dans .htaccess :
//     <Files "vault-config.php"> Deny from all </Files>
// ============================================================

if (!defined('ABSPATH')) { define('ABSPATH', true); }

// ── Mot de passe maître du coffre ─────────────────────────
// C'est le 2e facteur : différent de votre mot de passe de connexion
// Changez VAULT_MASTER_PASSWORD par votre mot de passe souhaité
// Le hash ci-dessous est généré automatiquement au premier accès
//
// Pour changer le mot de passe :
//   1. Changez VAULT_MASTER_PASSWORD
//   2. Régénérez le hash : php -r "echo password_hash('votre_mdp', PASSWORD_ARGON2ID);"
//   3. Remplacez VAULT_MASTER_HASH par le nouveau hash
//   ⚠️  Les anciens prompts ne seront PLUS déchiffrables (clé dérivée du mot de passe)
//   → Exportez-les AVANT de changer le mot de passe

define('VAULT_MASTER_HASH', '$argon2id$v=19$m=65536,t=4,p=1$Qkl3bHdyV2Rtbm1pT2lwUA$uEPlwYRGVCblmKJ4iaVAcXzvN1SsvEBcxAk/37Nra9M');
// → Générez votre hash avec :  php -r "echo password_verify('VOTRE_MDP', PASSWORD_ARGON2ID);"
// Exemple pour le mot de passe "MonCoffreFort2024!" :
// php -r "echo password_hash('MonCoffreFort2024!', PASSWORD_ARGON2ID);"

// ── Durée de session coffre (en secondes) ─────────────────
define('VAULT_SESSION_TTL', 900); // 15 minutes d'inactivité → verrouillage

// ── Sel de dérivation de clé de chiffrement ───────────────
// NE JAMAIS CHANGER après création des premiers prompts
// (les prompts existants deviendraient illisibles)
define('VAULT_KEY_SALT', 'StratEdge_Vault_2024_XK9mP3vQ7nR');
