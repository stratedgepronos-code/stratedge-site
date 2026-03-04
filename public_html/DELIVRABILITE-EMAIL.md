# Éviter que les emails StratEdge aillent en indésirables

Les mails partent depuis **noreply@stratedgepronos.fr**. Pour que Gmail, Orange, Free, etc. les mettent en **boîte de réception** et non en spam, il faut configurer **SPF**, **DKIM** et éventuellement **DMARC** sur le domaine **stratedgepronos.fr**.

---

## 1. SPF (Sender Policy Framework)

**Où :** DNS du domaine stratedgepronos.fr (chez ton hébergeur de domaine ou Hostinger/OVH).

**Quoi :** Un enregistrement **TXT** sur la racine du domaine (`stratedgepronos.fr`).

- **Si tes mails partent via l’hébergeur du site (ex. Hostinger)**  
  Dans la zone DNS, ajoute un enregistrement **TXT** :
  ```text
  v=spf1 include:_spf.hostinger.com ~all
  ```
  (Adapte `_spf.hostinger.com` selon ta doc : OVH = `include:mx.ovh.com`, etc.)

- **Si tu utilises un service d’email (ex. Brevo, SendGrid, Google Workspace)**  
  Inclus leurs serveurs, par ex. :
  ```text
  v=spf1 include:_spf.hostinger.com include:sendgrid.net ~all
  ```
  Un seul enregistrement SPF ; une seule chaîne `v=spf1 ... ~all`.

---

## 2. DKIM (signature des emails)

**Où :** Panel d’hébergement (cPanel, Hostinger, etc.) → section **Email** / **Authentification**.

- **Hostinger :**  
  Paramètres du domaine → **Email** → **Authentification email** (ou "Email Authentication") → **Activer DKIM** pour le domaine stratedgepronos.fr. L’hébergeur crée alors un enregistrement DNS (souvent un sous-domaine du type `default._domainkey`). Vérifie que le statut est "Actif" / "Verified".

- **OVH / autres :**  
  Même idée : activer DKIM pour le domaine depuis le panel, puis laisser le provider ajouter le TXT DKIM dans la zone DNS.

Une fois DKIM actif et vérifié, les mails envoyés depuis ce domaine sont signés et les boîtes mail (Gmail, etc.) leur font plus confiance.

---

## 3. DMARC (optionnel mais recommandé)

**Où :** DNS du domaine.

**Quoi :** Un enregistrement **TXT** sur le sous-domaine **\_dmarc** :

- Nom / sous-domaine : `_dmarc`
- Valeur (exemple) :
  ```text
  v=DMARC1; p=none; rua=mailto:stratedgepronos@gmail.com
  ```
  - `p=none` = ne pas rejeter les mails en cas d’échec (mode rapport uniquement).
  - Plus tard tu pourras passer à `p=quarantine` ou `p=reject` quand SPF + DKIM sont stables.

Cela permet de recevoir des rapports sur l’usage de ton domaine et d’améliorer la délivrabilité.

---

## 4. Vérifier la config

- **SPF :**  
  `nslookup -type=TXT stratedgepronos.fr` (ou outil en ligne "SPF check") : tu dois voir une entrée qui commence par `v=spf1`.

- **DKIM :**  
  Dans le panel email (Hostinger, etc.), le statut DKIM doit être "Actif" / "Verified".

- **Test d’envoi :**  
  Envoie un mail de test (inscription, reset mot de passe, etc.) vers une adresse Gmail puis vérifie les en-têtes (Affichage du message original) : tu dois voir "SPF: PASS" et "DKIM: PASS" (ou équivalent).

---

## 5. Si ça va encore en spam après SPF + DKIM

- Utiliser un **relais SMTP** (Brevo, SendGrid, Mailgun, Amazon SES) avec le domaine stratedgepronos.fr vérifié : ils gèrent SPF/DKIM et ont de bons taux de délivrabilité. Il faudrait alors adapter le code pour envoyer via SMTP (PHPMailer ou équivalent) au lieu de `mail()`.
- Demander aux membres d’ajouter **noreply@stratedgepronos.fr** ou **support@stratedgepronos.fr** à leurs contacts pour aider le filtre à les considérer comme sûrs.

---

**Résumé :**  
1) SPF sur stratedgepronos.fr (TXT avec `include` de ton hébergeur).  
2) DKIM activé dans le panel email du domaine.  
3) DMARC en `p=none` sur `_dmarc.stratedgepronos.fr`.  
4) Vérifier avec un envoi test et les en-têtes Gmail.

---

## 5. Conformité RGPD / LCEN (rapports d’abus, désinscription)

Pour limiter les signalements « spam » et respecter la loi (France/UE) :

- **Consentement à l’inscription** : case à cocher « J’accepte de recevoir les notifications par email » (optionnelle). Les nouveaux inscrits peuvent refuser.
- **Lien de désinscription** : présent dans **chaque** email (pied de page) et dans les en-têtes (`List-Unsubscribe` + `List-Unsubscribe-Post` pour le one-click Gmail). Lien : **https://stratedgepronos.fr/desabonnement-emails.php** (avec token pour désabonnement en un clic).
- **Préférences dans l’espace membre** : tableau de bord → Profil → « Préférences email » pour activer/désactiver les notifications sans cliquer dans un mail.
- **Exclusion des désinscrits** : les membres avec `accepte_emails = 0` ne reçoivent plus les envois « notification » (nouveaux bets, résultats, abonnement expiré, etc.). Les emails strictement transactionnels (réponse SAV, reset mot de passe, changement d’email) restent autorisés.

**Migration base** : exécuter une fois **admin/add_accepte_emails.sql** pour ajouter la colonne `accepte_emails` à la table `membres`.
