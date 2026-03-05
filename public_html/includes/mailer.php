<?php
// ============================================================
// STRATEDGE — Envoi d'emails
// ============================================================
//
// Pour éviter les indésirables (spam), en plus des headers ci‑dessous :
// 1. SPF  : DNS TXT sur stratedgepronos.fr avec "v=spf1 include:..." pour autoriser ton hébergeur (ex: Hostinger, OVH).
// 2. DKIM : Activer la signature DKIM dans le panel email (cPanel / Hostinger > Email > Authentication).
// 3. DMARC: DNS TXT _dmarc.stratedgepronos.fr (ex: "v=DMARC1; p=none; rua=mailto:...") pour renforcer la confiance.
// 4. Optionnel : utiliser un relais SMTP (Brevo, SendGrid, Mailgun) avec domaine vérifié = meilleure délivrabilité.
//
// ============================================================

if (!defined('SECRET_KEY')) {
    require_once __DIR__ . '/db.php';
}

/** Lien de désinscription (RGPD/LCEN) — un clic = désinscription */
function getUnsubscribeUrl(string $email): string {
    $e = base64_encode($email);
    $h = hash_hmac('sha256', $email, SECRET_KEY);
    return SITE_URL . '/desabonnement-emails.php?e=' . urlencode($e) . '&h=' . urlencode($h);
}

function envoyerEmail(string $to, string $sujet, string $htmlBody): bool {
    $from    = 'noreply@stratedgepronos.fr';
    $fromNom = 'StratEdge Pronos';

    $messageId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@stratedgepronos.fr>';
    $date      = date('r');

    $unsubUrl  = getUnsubscribeUrl($to);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromNom) . "?= <{$from}>\r\n";
    $headers .= "Reply-To: support@stratedgepronos.fr\r\n";
    $headers .= "Return-Path: <noreply@stratedgepronos.fr>\r\n";
    $headers .= "Message-ID: {$messageId}\r\n";
    $headers .= "Date: {$date}\r\n";
    // Lien désabonnement (obligatoire RGPD/LCEN — Gmail one-click)
    $headers .= "List-Unsubscribe: <{$unsubUrl}>\r\n";
    $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
    $headers .= "X-Priority: 3\r\n";
    // Ne pas mettre Precedence: bulk (favorise le classement en spam). Transactionnel = pas de Precedence ou "auto".
    $headers .= "Auto-Submitted: auto-generated\r\n";

    $body = quoted_printable_encode($htmlBody);

    return mail($to, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $body, $headers, '-f noreply@stratedgepronos.fr');
}

// ── Template HTML de base ─────────────────────────────────
// $emailForUnsubscribe : si fourni, affiche le lien de désinscription (RGPD/LCEN)
function emailTemplate(string $titre, string $contenu, ?string $emailForUnsubscribe = null): string {
    $footerDesabonnement = '';
    if ($emailForUnsubscribe !== null && $emailForUnsubscribe !== '') {
        $urlDesabo = getUnsubscribeUrl($emailForUnsubscribe);
        $footerDesabonnement = '<div style="margin-top:12px;"><a href="' . htmlspecialchars($urlDesabo) . '" style="color:#8a9bb0;font-size:0.75rem;text-decoration:underline;">Se désabonner des notifications par email</a></div>';
    }
    return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($titre) . '</title>
</head>
<body style="margin:0;padding:0;background:#0a0e17;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0e17;padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1a0a12,#0d1220);border-radius:16px 16px 0 0;padding:30px;text-align:center;border-bottom:3px solid #ff2d78;">
          <div style="font-size:1.8rem;font-weight:900;color:#ff2d78;letter-spacing:2px;font-family:Arial,sans-serif;">
            ⚡ STRATEDGE PRONOS
          </div>
          <div style="color:#8a9bb0;font-size:0.85rem;margin-top:5px;letter-spacing:1px;">
            Ta stratégie. Notre Edge. Leur défaite.
          </div>
        </td>
      </tr>

      <!-- Content -->
      <tr>
        <td style="background:#111827;padding:35px 40px;border-radius:0 0 16px 16px;">
          ' . $contenu . '
          
          <!-- Footer -->
          <div style="margin-top:30px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">
            <p style="color:#8a9bb0;font-size:0.75rem;margin:0;">
              © 2025 StratEdge Pronos — Tous droits réservés<br>
              Les paris sportifs comportent des risques. Jouez de manière responsable. Interdit aux −18 ans.
            </p>
            <div style="margin-top:10px;">
              <a href="https://stratedgepronos.fr" style="color:#ff2d78;font-size:0.8rem;text-decoration:none;">stratedgepronos.fr</a>
            </div>
            ' . $footerDesabonnement . '
          </div>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
}

// ── Email 1 : Bienvenue après inscription ─────────────────
function emailBienvenue(string $email, string $nom, string $password = ''): bool { // $password conservé pour rétrocompatibilité mais NON utilisé
    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">Bienvenue ' . htmlspecialchars($nom) . ' ! 🎉</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 25px;">
            Votre compte StratEdge Pronos a été créé avec succès. Conservez précieusement vos identifiants de connexion :
        </p>

        <!-- Identifiants -->
        <div style="background:rgba(255,45,120,0.06);border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:20px 25px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 15px;">🔐 Vos identifiants</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#8a9bb0;font-size:0.9rem;padding:6px 0;width:140px;">Adresse email :</td>
                    <td style="color:#f0f4f8;font-size:0.9rem;font-weight:bold;">' . htmlspecialchars($email) . '</td>
                </tr>
                <tr>
                    <td style="color:#8a9bb0;font-size:0.9rem;padding:6px 0;">Mot de passe :</td>
                    <td style="color:#ff2d78;font-size:0.9rem;font-weight:bold;font-family:monospace;">Celui que vous avez choisi à l\'inscription</td>
                </tr>
            </table>
        </div>

        <p style="color:#b0bec9;font-size:0.9rem;line-height:1.7;margin:0 0 25px;">
            Pour accéder aux bets, souscrivez à l\'une de nos formules depuis votre espace membre.
        </p>

        <!-- CTA -->
        <div style="text-align:center;margin:25px 0;">
            <a href="https://stratedgepronos.fr/login.php" 
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                Accéder à mon espace →
            </a>
        </div>

        <!-- Formules -->
        <div style="background:rgba(255,255,255,0.03);border-radius:10px;padding:15px 20px;margin-top:20px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 12px;">💡 Nos formules</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#b0bec9;font-size:0.88rem;padding:4px 0;">⚡ Daily — Prochain bet</td>
                    <td style="color:#ff2d78;font-weight:bold;font-size:0.88rem;text-align:right;">4,50€</td>
                </tr>
                <tr>
                    <td style="color:#b0bec9;font-size:0.88rem;padding:4px 0;">📅 Week-End — Vendredi au dimanche</td>
                    <td style="color:#ff2d78;font-weight:bold;font-size:0.88rem;text-align:right;">10€</td>
                </tr>
                <tr>
                    <td style="color:#b0bec9;font-size:0.88rem;padding:4px 0;">🏆 Weekly — 7 jours glissants</td>
                    <td style="color:#ff2d78;font-weight:bold;font-size:0.88rem;text-align:right;">20€</td>
                </tr>
            </table>
        </div>';

    return envoyerEmail($email, '⚡ Bienvenue sur StratEdge Pronos — Vos identifiants', emailTemplate('Bienvenue sur StratEdge', $contenu, $email));
}

// ── Email 2 : Confirmation d'abonnement ───────────────────
function emailConfirmationAbonnement(string $email, string $nom, string $type): bool {

    $typeInfos = [
        'daily' => [
            'label'      => '⚡ Daily — Prochain Bet',
            'montant'    => '4,50€',
            'duree'      => 'Valable jusqu\'au prochain bet publié',
            'contenu'    => 'Accès au prochain bet Safe + Live',
            'conditions' => 'Votre accès expire automatiquement dès que le prochain bet est publié sur la plateforme. Aucune reconduction automatique.',
        ],
        'weekend' => [
            'label'      => '📅 Week-End',
            'montant'    => '10€',
            'duree'      => 'Du vendredi 00h00 au dimanche 23h59',
            'contenu'    => 'Accès aux bets Safe & Fun + envoi des bets LIVE par SMS',
            'conditions' => 'Votre accès est valable pour le week-end en cours (vendredi 00h00 → dimanche 23h59), peu importe l\'heure d\'achat. Aucune reconduction automatique.',
        ],
        'weekly' => [
            'label'      => '🏆 Weekly — 7 jours',
            'montant'    => '20€',
            'duree'      => '7 jours glissants à partir de l\'achat',
            'contenu'    => 'Accès à tous les bets Safe & Fun + bets LIVE — Foot, NBA, Hockey...',
            'conditions' => 'Votre accès est valable 7 jours calendaires à partir de la date et heure d\'achat. Aucune reconduction automatique.',
        ],
    ];

    $info = $typeInfos[$type] ?? $typeInfos['daily'];
    $dateAchat = date('d/m/Y à H:i');

    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">Abonnement confirmé ! ✅</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 25px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Votre paiement a été validé. Votre accès aux bets StratEdge est maintenant actif.
        </p>

        <!-- Récapitulatif abonnement -->
        <div style="background:rgba(255,45,120,0.06);border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:20px 25px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 15px;">📋 Récapitulatif de votre abonnement</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#8a9bb0;font-size:0.88rem;padding:6px 0;width:160px;">Formule :</td>
                    <td style="color:#ff2d78;font-size:0.95rem;font-weight:bold;">' . $info['label'] . '</td>
                </tr>
                <tr>
                    <td style="color:#8a9bb0;font-size:0.88rem;padding:6px 0;">Montant payé :</td>
                    <td style="color:#f0f4f8;font-size:0.88rem;font-weight:bold;">' . $info['montant'] . '</td>
                </tr>
                <tr>
                    <td style="color:#8a9bb0;font-size:0.88rem;padding:6px 0;">Date d\'achat :</td>
                    <td style="color:#f0f4f8;font-size:0.88rem;">' . $dateAchat . '</td>
                </tr>
                <tr>
                    <td style="color:#8a9bb0;font-size:0.88rem;padding:6px 0;">Durée :</td>
                    <td style="color:#f0f4f8;font-size:0.88rem;">' . $info['duree'] . '</td>
                </tr>
                <tr>
                    <td style="color:#8a9bb0;font-size:0.88rem;padding:6px 0;">Accès inclus :</td>
                    <td style="color:#f0f4f8;font-size:0.88rem;">' . $info['contenu'] . '</td>
                </tr>
            </table>
        </div>

        <!-- CTA -->
        <div style="text-align:center;margin:25px 0;">
            <a href="https://stratedgepronos.fr/bets.php" 
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                📊 Accéder aux bets →
            </a>
        </div>

        <!-- Conditions -->
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:15px 20px;margin-top:20px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 8px;">📌 Conditions d\'utilisation</p>
            <p style="color:#8a9bb0;font-size:0.82rem;line-height:1.6;margin:0;">
                ' . $info['conditions'] . '<br><br>
                En cas de problème, contactez le support via votre espace membre ou à 
                <a href="mailto:stratedgepronos@gmail.com" style="color:#ff2d78;">stratedgepronos@gmail.com</a>.
            </p>
        </div>';

    return envoyerEmail($email, '✅ Abonnement ' . strip_tags($info['label']) . ' activé — StratEdge Pronos', emailTemplate('Confirmation abonnement', $contenu, $email));
}

// ── Email 3 : Nouveau bet disponible ─────────────────────
function emailNouveauBet(string $email, string $nom, string $typeBet, string $titrebet = ''): bool {
    $typeLabels = ['safe' => '🛡️ Safe', 'fun' => '🎯 Fun', 'live' => '⚡ Live', 'safe,fun' => '🛡️+🎯 Safe+Fun', 'safe,live' => '🛡️+⚡ Safe+Live'];
    $typeLabel  = $typeLabels[$typeBet] ?? $typeBet;
    $titreLine  = $titrebet ? '<strong style="color:#ff2d78;">' . htmlspecialchars($titrebet) . '</strong>' : 'une nouvelle analyse';

    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">🔥 Nouveau bet disponible !</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Un nouveau bet vient d\'être posté sur StratEdge Pronos. Connecte-toi maintenant pour y accéder !
        </p>
        <div style="background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);border-radius:12px;padding:20px 25px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 12px;">📊 Détails du bet</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="color:#8a9bb0;font-size:0.9rem;padding:5px 0;width:120px;">Analyse :</td>
                    <td style="color:#f0f4f8;font-size:0.9rem;">' . $titreLine . '</td></tr>
                <tr><td style="color:#8a9bb0;font-size:0.9rem;padding:5px 0;">Type :</td>
                    <td style="color:#ff2d78;font-size:0.9rem;font-weight:bold;">' . $typeLabel . '</td></tr>
                <tr><td style="color:#8a9bb0;font-size:0.9rem;padding:5px 0;">Posté le :</td>
                    <td style="color:#f0f4f8;font-size:0.9rem;">' . date('d/m/Y à H:i') . '</td></tr>
            </table>
        </div>
        <div style="text-align:center;margin:25px 0;">
            <a href="https://stratedgepronos.fr/bets.php"
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                🔥 Voir le bet maintenant →
            </a>
        </div>
        <p style="color:#8a9bb0;font-size:0.82rem;text-align:center;line-height:1.6;">
            Votre abonnement est actif — profitez-en !<br>
            <a href="https://stratedgepronos.fr/bets.php" style="color:#ff2d78;">stratedgepronos.fr/bets.php</a>
        </p>';

    return envoyerEmail($email, '🔥 Nouveau bet disponible — ' . strip_tags($typeLabel) . ' | StratEdge', emailTemplate('Nouveau bet', $contenu, $email));
}

// ── Email 4 : Abonnement expiré ───────────────────────────
function emailAbonnementExpire(string $email, string $nom, string $type): bool {
    $typeLabels = ['daily' => 'Daily', 'weekend' => 'Week-End', 'weekly' => 'Weekly 7 jours', 'tennis' => 'Tennis Weekly'];
    $typeLabel  = $typeLabels[$type] ?? $type;

    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">⏰ Votre abonnement est terminé</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Votre abonnement <strong style="color:#ff2d78;">' . $typeLabel . '</strong> a expiré.
            Les prochains bets seront de nouveau verrouillés pour vous.
        </p>
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:20px 25px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 15px;">🔓 Revenez avec nos formules</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#b0bec9;font-size:0.88rem;padding:6px 0;">⚡ Daily — Prochain bet</td>
                    <td style="color:#ff2d78;font-weight:bold;font-size:0.88rem;text-align:right;">4,50€</td>
                </tr>
                <tr>
                    <td style="color:#b0bec9;font-size:0.88rem;padding:6px 0;">📅 Week-End — Ven → Dim</td>
                    <td style="color:#ff2d78;font-weight:bold;font-size:0.88rem;text-align:right;">10€</td>
                </tr>
                <tr>
                    <td style="color:#b0bec9;font-size:0.88rem;padding:6px 0;">🏆 Weekly — 7 jours</td>
                    <td style="color:#ff2d78;font-weight:bold;font-size:0.88rem;text-align:right;">20€</td>
                </tr>
            </table>
        </div>
        <div style="text-align:center;margin:25px 0;">
            <a href="https://stratedgepronos.fr/#pricing"
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                🔒 Me réabonner maintenant →
            </a>
        </div>
        <div style="background:rgba(255,45,120,0.04);border-radius:10px;padding:15px;text-align:center;">
            <p style="color:#8a9bb0;font-size:0.82rem;margin:0;">
                Ne ratez pas les prochaines analyses — les bets sont réguliers et exclusifs.<br>
                <strong style="color:#ff2d78;">Rejoignez les gagnants sur StratEdge Pronos.</strong>
            </p>
        </div>';

    return envoyerEmail($email, '⏰ Votre abonnement ' . $typeLabel . ' est terminé — StratEdge Pronos', emailTemplate('Abonnement expiré', $contenu, $email));
}

// ── Email 5 : Résultat d'un bet ───────────────────────────
function emailResultatBet(string $email, string $nom, string $titre, string $resultat, string $typeBet): bool {
    $icons  = ['win' => '✅', 'lose' => '❌', 'void' => '↩️'];
    $labels = ['win' => 'GAGNANT', 'lose' => 'PERDANT', 'void' => 'ANNULÉ'];
    $colors = ['win' => '#00d46a', 'lose' => '#ff2d78', 'void' => '#ffc107'];
    $icon   = $icons[$resultat]  ?? '📊';
    $label  = $labels[$resultat] ?? strtoupper($resultat);
    $color  = $colors[$resultat] ?? '#8a9bb0';

    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">' . $icon . ' Résultat du bet</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Le résultat de l\'analyse suivante vient d\'être publié :
        </p>
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:20px 25px;margin-bottom:25px;text-align:center;">
            <p style="color:#8a9bb0;font-size:0.8rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 10px;">Analyse</p>
            <p style="color:#f0f4f8;font-size:1.1rem;font-weight:700;margin:0 0 15px;">' . htmlspecialchars($titre ?: 'Bet StratEdge') . '</p>
            <span style="background:' . $color . '22;border:2px solid ' . $color . ';color:' . $color . ';border-radius:8px;padding:8px 24px;font-family:monospace;font-size:1.3rem;font-weight:900;letter-spacing:3px;">' . $icon . ' ' . $label . '</span>
        </div>
        <div style="text-align:center;margin:25px 0;">
            <a href="https://stratedgepronos.fr/bets.php"
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;">
                📊 Voir tous les bets →
            </a>
        </div>';

    return envoyerEmail($email, $icon . ' Résultat : ' . $label . ' — StratEdge Pronos', emailTemplate('Résultat du bet', $contenu, $email));
}

// ── Email 6 : Réponse ticket SAV ──────────────────────────
function emailReponseTicket(string $email, string $nom, string $sujet, string $reponse): bool {
    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">🎫 Réponse à ton ticket</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            L\'équipe StratEdge a répondu à ton ticket :
        </p>
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:15px 20px;margin-bottom:10px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 6px;">Sujet</p>
            <p style="color:#fff;font-weight:700;margin:0;">' . htmlspecialchars($sujet) . '</p>
        </div>
        <div style="background:rgba(0,212,106,0.06);border-left:3px solid #00d46a;border-radius:0 10px 10px 0;padding:15px 20px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 8px;">Réponse</p>
            <p style="color:#e2e8f0;font-size:0.9rem;line-height:1.7;margin:0;">' . nl2br(htmlspecialchars($reponse)) . '</p>
        </div>
        <div style="text-align:center;">
            <a href="https://stratedgepronos.fr/sav.php"
               style="display:inline-block;background:linear-gradient(135deg,#00d46a,#00a854);color:#000;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;">
                🎫 Voir mon ticket →
            </a>
        </div>';

    return envoyerEmail($email, '🎫 Réponse à ton ticket — StratEdge Pronos', emailTemplate('Réponse ticket SAV', $contenu, $email));
}

// ── Email 7 : Nouveau message chat ────────────────────────
function emailNouveauMessageChat(string $email, string $nom, string $contenuMsg): bool {
    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">💬 Nouveau message</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            L\'équipe StratEdge t\'a envoyé un message :
        </p>
        <div style="background:rgba(0,212,106,0.06);border-left:3px solid #00d46a;border-radius:0 10px 10px 0;padding:15px 20px;margin-bottom:25px;">
            ' . nl2br(htmlspecialchars($contenuMsg)) . '
        </div>
        <div style="text-align:center;">
            <a href="https://stratedgepronos.fr/chat.php"
               style="display:inline-block;background:linear-gradient(135deg,#00d46a,#00a854);color:#000;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;">
                💬 Répondre →
            </a>
        </div>';

    return envoyerEmail($email, '💬 Nouveau message — StratEdge Pronos', emailTemplate('Nouveau message', $contenu, $email));
}


// ── Email 8 : Reset mot de passe ─────────────────────────────
function emailResetPassword(string $email, string $nom, string $token): bool {
    $lien = SITE_URL . '/reset-password.php?token=' . urlencode($token);

    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">🔑 Mot de passe</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Une demande de modification de mot de passe a bien été enregistrée sur ton compte StratEdge Pronos.
        </p>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 25px;">
            Clique sur le bouton ci-dessous pour choisir un nouveau mot de passe :
        </p>
        <div style="text-align:center;margin:25px 0;">
            <a href="' . htmlspecialchars($lien) . '"
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                Modifier mon mot de passe
            </a>
        </div>
        <div style="background:rgba(255,45,120,0.06);border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:20px 25px;margin-bottom:20px;">
            <p style="color:#8a9bb0;font-size:0.82rem;margin:0 0 8px;">⏳ <strong style="color:#f0f4f8;">Ce lien expire dans 30 minutes.</strong></p>
            <p style="color:#8a9bb0;font-size:0.82rem;margin:0;">
                Si tu n\'as pas fait cette demande, ignore simplement cet email. Ton compte reste en toute sécurité.
            </p>
        </div>
        <p style="color:#8a9bb0;font-size:0.78rem;margin:0;">
            Lien direct : <a href="' . htmlspecialchars($lien) . '" style="color:#ff2d78;word-break:break-all;">' . htmlspecialchars($lien) . '</a>
        </p>';

    return envoyerEmail($email, '🔑 Modifier ton mot de passe — StratEdge Pronos', emailTemplate('Mot de passe', $contenu, $email));
}

// ── Email 9 : Confirmation changement email ──────────────────
function emailChangementEmail(string $ancienEmail, string $nom, string $nouvelEmail): bool {

    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">📧 Changement d\'adresse email</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Ton adresse email a bien été modifiée sur StratEdge Pronos.
        </p>
        <div style="background:rgba(255,45,120,0.06);border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:20px 25px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 15px;">📋 Modification</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#8a9bb0;font-size:0.9rem;padding:6px 0;width:160px;">Ancienne adresse :</td>
                    <td style="color:#f0f4f8;font-size:0.9rem;font-weight:bold;">' . htmlspecialchars($ancienEmail) . '</td>
                </tr>
                <tr>
                    <td style="color:#8a9bb0;font-size:0.9rem;padding:6px 0;">Nouvelle adresse :</td>
                    <td style="color:#ff2d78;font-size:0.9rem;font-weight:bold;">' . htmlspecialchars($nouvelEmail) . '</td>
                </tr>
            </table>
        </div>
        <p style="color:#b0bec9;font-size:0.85rem;line-height:1.7;margin:0 0 25px;">
            Si tu es bien l\'auteur de cette modification, aucune action n\'est requise.<br>
            Si ce changement ne vient pas de toi, contacte le support via le SAV.
        </p>
        <div style="text-align:center;margin:25px 0;">
            <a href="https://stratedgepronos.fr/sav.php"
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                🎫 Contacter le support →
            </a>
        </div>';

    return envoyerEmail($ancienEmail, '📧 Ton adresse email a été modifiée — StratEdge Pronos', emailTemplate('Changement email', $contenu, $ancienEmail));
}

// ── Email 10 : Anniversaire + code promo (1×/an, envoyé par cron) ─────────────
function emailAnniversaireCodePromo(string $email, string $nom, string $codePromo): bool {
    $lienOffres = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://stratedgepronos.fr') . '/offres.php';
    $contenu = '
        <h2 style="color:#f0f4f8;font-size:1.4rem;margin:0 0 10px;">🎂 Joyeux anniversaire !</h2>
        <p style="color:#b0bec9;font-size:0.95rem;line-height:1.7;margin:0 0 20px;">
            Bonjour <strong style="color:#f0f4f8;">' . htmlspecialchars($nom) . '</strong>,<br>
            Toute l\'équipe StratEdge te souhaite un très bon anniversaire. 🎉
        </p>
        <div style="background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);border-radius:12px;padding:22px 25px;margin-bottom:25px;">
            <p style="color:#8a9bb0;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;margin:0 0 12px;">🎁 Ton code promo anniversaire</p>
            <p style="color:#f0f4f8;font-size:1.1rem;font-weight:700;margin:0 0 8px;letter-spacing:1px;">' . htmlspecialchars($codePromo) . '</p>
            <p style="color:#8a9bb0;font-size:0.85rem;margin:0 0 12px;">
                • <strong style="color:#f0f4f8;">-50%</strong> sur les formules Tennis, Daily, Weekly et Week-end<br>
                • <strong style="color:#f0f4f8;">-25%</strong> sur l\'offre VIP Max
            </p>
            <p style="color:#8a9bb0;font-size:0.8rem;margin:0;">Valable <strong>une seule fois</strong> cette année. Saisis ce code sur la page de paiement de l\'offre choisie.</p>
        </div>
        <div style="text-align:center;margin:25px 0;">
            <a href="' . htmlspecialchars($lienOffres) . '"
               style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:white;padding:14px 35px;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;letter-spacing:1px;text-transform:uppercase;">
                Voir les offres →
            </a>
        </div>
        <p style="color:#8a9bb0;font-size:0.8rem;margin:20px 0 0;">À très vite sur StratEdge Pronos ! ⚡</p>';

    return envoyerEmail($email, '🎂 Joyeux anniversaire — Ton code promo StratEdge', emailTemplate('Anniversaire', $contenu, $email));
}
