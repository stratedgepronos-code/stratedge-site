<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../includes/tweet-ai.php';
requireAdmin();
$pageActive    = 'poster-bet';
$isSuperAdmin  = isSuperAdmin();
$adminRole     = getAdminRole();

// Charger config Make/Twitter si configuré
$twitterActif  = false;
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
$nbPostes = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';
    } elseif (isset($_POST['action'])) {

        if ($_POST['action'] === 'post_bets') {
            $titres       = $_POST['titre']         ?? [];
            $types        = $_POST['type']          ?? [];
            $descs        = $_POST['description']   ?? [];
            $categories   = $_POST['categorie']     ?? [];
            $sports       = $_POST['sport']        ?? [];
            $analyseHtmls = $_POST['analyse_html']  ?? [];
            $cotes        = $_POST['cote']          ?? [];
            $images      = $_FILES['images'] ?? [];
            $expireDaily = isset($_POST['expire_daily']);

            // Normaliser $_FILES : en single file, PHP peut renvoyer name/tmp_name en string
            if (!empty($images['name']) && !is_array($images['name'])) {
                $images = [
                    'name'     => [$images['name']],
                    'type'     => [$images['type'] ?? ''],
                    'tmp_name' => [$images['tmp_name'] ?? ''],
                    'error'    => [$images['error'] ?? UPLOAD_ERR_NO_FILE],
                    'size'     => [$images['size'] ?? 0],
                ];
            }
            $lockedImages = $_FILES['locked_images'] ?? [];
            if (!empty($lockedImages['name']) && !is_array($lockedImages['name'])) {
                $lockedImages = [
                    'name'     => [$lockedImages['name']],
                    'type'     => [$lockedImages['type'] ?? ''],
                    'tmp_name' => [$lockedImages['tmp_name'] ?? ''],
                    'error'    => [$lockedImages['error'] ?? UPLOAD_ERR_NO_FILE],
                    'size'     => [$lockedImages['size'] ?? 0],
                ];
            }

            $nbFichiers = count($images['name'] ?? []);

            if ($nbFichiers === 0) {
                $error = 'Veuillez sélectionner au moins une image.';
            } else {
                try {
                $uploadDir  = __DIR__ . '/../uploads/bets/';
                $lockedDir  = __DIR__ . '/../uploads/locked/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                if (!is_dir($lockedDir)) @mkdir($lockedDir, 0755, true);
                if (!is_writable($uploadDir)) {
                    $error = 'Le dossier uploads/bets/ n\'est pas accessible en écriture. Vérifie les droits sur le serveur (CHMOD 755 ou 775).';
                } else {

                $lastType        = 'safe';
                $lastTitre       = '';
                $lastImagePath   = '';
                $lastLockedPath  = '';
                $lastCategorie   = 'multi';
                $twitterMsg      = '';

                // DEBUG locked (ne pas faire échouer l'envoi)
                try {
                    @file_put_contents(__DIR__ . '/../locked_debug.log',
                        date('Y-m-d H:i:s') . " | nbFichiers=" . $nbFichiers
                        . " | locked keys=" . json_encode(array_keys($lockedImages['name'] ?? []))
                        . " | locked errors=" . json_encode($lockedImages['error'] ?? [])
                        . " | locked names=" . json_encode($lockedImages['name'] ?? [])
                        . "\n", FILE_APPEND);
                } catch (Throwable $e) { /* ignore */ }

                for ($i = 0; $i < $nbFichiers; $i++) {
                    if ($images['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($images['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                    if ($images['size'][$i] > 10 * 1024 * 1024) continue;
                    // Vérification MIME réelle (anti double-extension, ex: shell.php.jpg)
                    $finfo    = new finfo(FILEINFO_MIME_TYPE);
                    $mimeReal = $finfo->file($images['tmp_name'][$i]);
                    $allowedMimes = ['image/jpeg','image/png','image/webp','image/gif'];
                    if (!in_array($mimeReal, $allowedMimes)) continue;

                    $filename = 'bet_' . time() . '_' . $i . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($images['tmp_name'][$i], $uploadDir . $filename)) {
                        $lastTitre     = trim($titres[$i] ?? '');
                        $lastType      = $types[$i] ?? 'safe';
                        $lastCategorie = in_array($categories[$i] ?? '', ['multi','tennis']) ? $categories[$i] : 'multi';
                        if ($adminRole === 'admin_tennis') {
                            $lastCategorie = 'tennis';
                            $lastType = $types[$i] ?? 'safe';
                        } elseif ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport') {
                            // Admin Fun: tous types autorises (safe/live/fun/safecombi), categorie multi forcee
                            $lastCategorie = 'multi';
                            $lastType = $types[$i] ?? 'fun';
                        }
                        if ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport') {
                            // Sports admin Fun: foot/basket/hockey/baseball uniquement (jamais tennis)
                            $lastSport = in_array($sports[$i] ?? '', ['football','basket','hockey','baseball']) ? $sports[$i] : 'football';
                        } else {
                            $lastSport = ($lastCategorie === 'tennis') ? 'tennis' : (in_array($sports[$i] ?? '', ['football','tennis','basket','hockey','baseball']) ? $sports[$i] : 'football');
                        }
                        $lastAnalyseHtml = trim((string)($analyseHtmls[$i] ?? ''));
                        $lastCote = trim((string)($cotes[$i] ?? ''));
                        $lastCote = ($lastCote !== '' && is_numeric($lastCote)) ? number_format((float)$lastCote, 2, '.', '') : null;
                        $lastImagePath = 'uploads/bets/' . $filename;

                        // ── Locked image : uploadée manuellement si fournie ──
                        $lastLockedPath = '';
                        if (isset($lockedImages['tmp_name'][$i], $lockedImages['error'][$i]) && !empty($lockedImages['tmp_name'][$i]) && $lockedImages['error'][$i] === UPLOAD_ERR_OK) {
                            $lext = strtolower(pathinfo($lockedImages['name'][$i] ?? '', PATHINFO_EXTENSION));
                            if (in_array($lext, ['jpg','jpeg','png','webp','gif'])) {
                                $lname = 'locked_' . time() . '_' . $i . '_' . bin2hex(random_bytes(3)) . '.' . $lext;
                                if (move_uploaded_file($lockedImages['tmp_name'][$i], $lockedDir . $lname)) {
                                    $lastLockedPath = 'uploads/locked/' . $lname;
                                }
                            }
                        }

                        // Lire les binaires des images pour backup BDD
                        $imageBlob  = @file_get_contents($uploadDir . $filename);
                        $lockedBlob = ($lastLockedPath !== '' && isset($lname)) ? @file_get_contents($lockedDir . $lname) : null;

                        try {
                            $stmt = $db->prepare("INSERT INTO bets (titre, image_path, image_data, locked_image_path, locked_image_data, type, categorie, sport, description, analyse_html, cote) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$lastTitre, $lastImagePath, $imageBlob ?: null, $lastLockedPath ?: null, $lockedBlob ?: null, $lastType, $lastCategorie, $lastSport, trim($descs[$i] ?? ''), $lastAnalyseHtml ?: null, $lastCote]);
                        } catch (Throwable $insErr) {
                            error_log('[poster-bet] INSERT full échoué, fallback avec blobs : ' . $insErr->getMessage());
                            try {
                                // Sauvegarde des images en BDD (backup si fichiers supprimés au push/déploiement)
                                $stmt = $db->prepare("INSERT INTO bets (titre, image_path, image_data, locked_image_path, locked_image_data, type, categorie, sport, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$lastTitre, $lastImagePath, $imageBlob ?: null, $lastLockedPath ?: null, $lockedBlob ?: null, $lastType, $lastCategorie, $lastSport, trim($descs[$i] ?? '')]);
                            } catch (Throwable $insErr2) {
                                error_log('[poster-bet] INSERT avec blobs échoué : ' . $insErr2->getMessage());
                                try {
                                    $stmt = $db->prepare("INSERT INTO bets (titre, image_path, locked_image_path, type, categorie, sport, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([$lastTitre, $lastImagePath, $lastLockedPath ?: null, $lastType, $lastCategorie, $lastSport, trim($descs[$i] ?? '')]);
                                } catch (Throwable $insErr3) {
                                    try {
                                        $stmt = $db->prepare("INSERT INTO bets (titre, image_path, locked_image_path, type, categorie, description) VALUES (?, ?, ?, ?, ?, ?)");
                                        $stmt->execute([$lastTitre, $lastImagePath, $lastLockedPath ?: null, $lastType, $lastCategorie, trim($descs[$i] ?? '')]);
                                    } catch (Throwable $insErr4) {
                                        $stmt = $db->prepare("INSERT INTO bets (titre, image_path, type) VALUES (?, ?, ?)");
                                        $stmt->execute([$lastTitre, $lastImagePath, $lastType]);
                                    }
                                }
                            }
                        }

                        // Tag posted_by_role selon le role admin (pour routage tipster cote membre)
                        try {
                            $betId = $db->lastInsertId();
                            if ($betId) {
                                $roleToTag = 'superadmin';
                                if ($adminRole === 'admin_tennis') $roleToTag = 'admin_tennis';
                                elseif ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport') $roleToTag = 'admin_fun';
                                $db->prepare("UPDATE bets SET posted_by_role=? WHERE id=?")->execute([$roleToTag, $betId]);
                            }
                        } catch (Throwable $eTag) { /* silencieux si colonne pas migree */ }

                        $nbPostes++;

                        // ── Tweet immédiat pour CETTE image ──
                        if ($twitterActif) {
                            // Determine role (variable existante en haut du handler) pour hashtags
                            $roleForTweet = ($adminRole === 'admin_tennis') ? 'admin_tennis'
                                          : (($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport') ? 'admin_fun' : 'superadmin');
                            $texte    = twitterPhrase($lastType, $lastTitre, $roleForTweet);
                            $imageChoisie = $lastLockedPath ?: $lastImagePath;
                            $imageDir = (strpos($imageChoisie, 'locked') !== false) ? 'locked' : 'bets';
                            $imageUrl = 'https://stratedgepronos.fr/restore-image.php?dir=' . $imageDir . '&file=' . rawurlencode(basename($imageChoisie));

                            // LOG DEBUG
                            $logLine = date('Y-m-d H:i:s')
                                . " | lastImagePath=" . $lastImagePath
                                . " | lastLockedPath=" . ($lastLockedPath ?: 'VIDE')
                                . " | imageUrl=" . $imageUrl
                                . " | fichier_existe=" . (file_exists(__DIR__ . '/../' . $imageChoisie) ? 'OUI' : 'NON')
                                . "\n";
                            file_put_contents(__DIR__ . '/../twitter_debug.log', $logLine, FILE_APPEND);

                            // Choisir le bon webhook : avec image si dispo, sinon texte seul
                            // (identique à twitter-post.php qui fonctionne)
                            if (!empty($twitterConfig['webhook_url_image'])) {
                                $webhookUrl = $twitterConfig['webhook_url_image'];
                                $payload = json_encode([
                                    'value1' => $texte,
                                    'value2' => 'StratEdge Pronos',
                                    'value3' => $imageUrl,
                                ], JSON_UNESCAPED_UNICODE);
                            } else {
                                $webhookUrl = $twitterConfig['webhook_url'];
                                $payload = json_encode([
                                    'value1' => $texte,
                                    'value2' => 'StratEdge Pronos',
                                    'value3' => '',
                                ], JSON_UNESCAPED_UNICODE);
                            }

                            $ch = curl_init($webhookUrl);
                            curl_setopt_array($ch, [
                                CURLOPT_POST           => true,
                                CURLOPT_POSTFIELDS     => $payload,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                                CURLOPT_TIMEOUT        => 10,
                            ]);
                            $twResponse = curl_exec($ch);
                            $twCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            // LOG réponse IFTTT
                            file_put_contents(__DIR__ . '/../twitter_debug.log',
                                date('Y-m-d H:i:s') . " | webhook=" . $webhookUrl . " | IFTTT HTTP=" . $twCode . " | response=" . $twResponse . "\n",
                                FILE_APPEND);

                            $twitterMsg .= ($twCode >= 200 && $twCode < 300)
                                ? ' 🐦 Tweet ' . ($i+1) . ' envoyé ! (image: ' . basename($imageChoisie) . ')'
                                : ' ⚠️ Tweet ' . ($i+1) . ' IFTTT HTTP ' . $twCode;
                        }
                    }
                }

                if ($nbPostes > 0) {
                    try {
                    require_once __DIR__ . '/../includes/notif-queue.php';
                    $batch = notifQueueEnqueueNouveauBet($db, $lastCategorie ?? 'multi', $lastType, $lastTitre);
                    $stc = $db->prepare('SELECT COUNT(*) FROM notif_queue WHERE batch_id = ?');
                    $stc->execute([$batch]);
                    $nbQueued = (int)$stc->fetchColumn();

                    $notifLog = date('Y-m-d H:i:s') . " | batch={$batch} | queued={$nbQueued} | cat=" . ($lastCategorie ?? '') . " | type={$lastType}\n";

                    $emailsOk = 0;
                    $emailsFail = 0;
                    $pushOk = 0;
                    $pushNone = 0;
                    $procTotal = 0;
                    for ($r = 0; $r < 40; $r++) {
                        $st = notifQueueProcessBatch($db, 100);
                        $procTotal += $st['processed'];
                        $emailsOk += $st['emails_ok'];
                        $emailsFail += $st['emails_fail'];
                        $pushOk += $st['push_ok'];
                        $pushNone += $st['push_none'];
                        if ($st['processed'] === 0) {
                            break;
                        }
                    }
                    $reste = notifQueuePendingCount($db);
                    $notifLog .= "  → Traités cette passe: {$procTotal} | emails OK={$emailsOk} fail={$emailsFail} | push OK={$pushOk} sans_sub={$pushNone} | reste_file={$reste}\n";
                    if ($reste > 0) {
                        $notifLog .= "  → CRON: cron/process-notif-queue.php?token=... pour vider la file\n";
                    }
                    file_put_contents(__DIR__ . '/../notif_debug.log', $notifLog, FILE_APPEND);

                    if ($expireDaily) {
                        $dailyMembres = $db->query("
                            SELECT DISTINCT m.id, m.email, m.nom FROM membres m
                            JOIN abonnements a ON a.membre_id = m.id
                            WHERE a.type = 'daily' AND a.actif = 1
                        ")->fetchAll();
                        $db->exec("UPDATE abonnements SET actif = 0 WHERE type = 'daily' AND actif = 1");
                        foreach ($dailyMembres as $dm) {
                            emailAbonnementExpire($dm['email'], $dm['nom'], 'daily');
                            pushAbonnementExpire((int)$dm['id'], 'daily');
                        }
                        $success = $nbPostes . ' bet' . ($nbPostes > 1 ? 's postés' : ' posté') . ' ✅ — Daily expirés + emails.' . $twitterMsg;
                    } else {
                        $success = $nbPostes . ' bet' . ($nbPostes > 1 ? 's postés' : ' posté') . ' ✅ — Emails envoyés.' . $twitterMsg;
                    }
                    } catch (Throwable $notifE) {
                        error_log('[poster-bet] notifications: ' . $notifE->getMessage() . ' in ' . $notifE->getFile() . ':' . $notifE->getLine() . "\n" . $notifE->getTraceAsString());
                        $success = $nbPostes . ' bet' . ($nbPostes > 1 ? 's postés' : ' posté') . ' ✅ (notifications partiellement en erreur).' . $twitterMsg;
                    }
                } else {
                    $error = 'Aucun fichier valide uploadé.';
                }
                }
                } catch (Throwable $e) {
                    error_log('[poster-bet] post_bets: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
                    $error = 'Erreur post_bets : ' . mb_substr($e->getMessage(), 0, 200) . ' — Vérifie les logs serveur.';
                }
            }
        }

        // ── DÉFINIR LE RÉSULTAT D'UN BET ──────────────────────
        elseif ($_POST['action'] === 'set_resultat') {
            $betId    = (int)($_POST['bet_id'] ?? 0);
            $resultat = $_POST['resultat'] ?? '';
            if ($betId && in_array($resultat, ['gagne','perdu','annule'])) {
                $bet = null;
                try {
                    $betInfo = $db->prepare("SELECT * FROM bets WHERE id = ?");
                    $betInfo->execute([$betId]);
                    $bet = $betInfo->fetch(PDO::FETCH_ASSOC);

                    // UPDATE : compatible même si resultat/date_resultat n'existent pas encore
                    try {
                        $db->prepare("UPDATE bets SET resultat=?, date_resultat=NOW(), actif=0 WHERE id=?")
                           ->execute([$resultat, $betId]);
                    } catch (Throwable $colErr) {
                        // Colonne resultat/date_resultat absente → fallback simple
                        $db->prepare("UPDATE bets SET actif=0 WHERE id=?")->execute([$betId]);
                        error_log('[poster-bet] set_resultat colonne resultat manquante, fallback actif=0 : ' . $colErr->getMessage());
                    }
                } catch (Throwable $e) {
                    error_log('[poster-bet] set_resultat UPDATE: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
                    $error = 'Erreur résultat : ' . mb_substr($e->getMessage(), 0, 150) . ' — Vérifie les logs serveur.';
                }

                if (empty($error)) {
                    $resMap = ['gagne' => 'win', 'perdu' => 'lose', 'annule' => 'void'];
                    $resCode = $resMap[$resultat] ?? $resultat;
                    $titreResult = ($bet && isset($bet['titre'])) ? $bet['titre'] : 'Bet StratEdge';

                    // ── Push + Email résultat (file d’attente : pas de timeout + super admin inclus) ──
                    try {
                        require_once __DIR__ . '/../includes/notif-queue.php';
                        resultatQueueEnqueue($db, $titreResult, $resCode);
                        $pendingR = resultatQueuePendingCount($db);
                        $totalR = 0;
                        for ($ri = 0; $ri < 45; $ri++) {
                            $stR = resultatQueueProcessBatch($db, 90);
                            $totalR += $stR['processed'];
                            if ($stR['processed'] === 0) {
                                break;
                            }
                        }
                        $leftR = resultatQueuePendingCount($db);
                        @file_put_contents(__DIR__ . '/../notif_debug.log', date('c') . " resultat_queue emails_ok={$totalR} pending_end={$leftR} titre=" . mb_substr($titreResult, 0, 80) . "\n", FILE_APPEND | LOCK_EX);
                        if ($leftR > 0) {
                            error_log('[poster-bet] set_resultat file résultat reste=' . $leftR . ' — lancer cron process-notif-queue.php');
                        }
                    } catch (Throwable $e) {
                        error_log('[poster-bet] set_resultat push/email: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    }

                    // ── Tweet résultat via IFTTT / Make (ne pas faire échouer la page) ──
                    // Note: on NE tweete PAS les bets perdus (évite la sur-exposition des pertes)
                    // Tweet uniquement pour: gagné (célébration) + annulé (remboursement info)
                    $twitterResultMsg = '';
                    try {
                        if ($twitterActif && !empty($twitterConfig['webhook_url']) && $bet && $resultat !== 'perdu') {
                            $titre = !empty($bet['titre']) ? ' — ' . $bet['titre'] : '';
                            $coteStr = !empty($bet['cote']) ? ' (cote ' . $bet['cote'] . ')' : '';
                            $roleOriginal = $bet['posted_by_role'] ?? 'superadmin';

                            // Emoji et label adaptés selon le tipster
                            $isTennis = ($roleOriginal === 'admin_tennis');
                            $isFun    = in_array($roleOriginal, ['admin_fun', 'admin_fun_sport']);

                            if ($isTennis) {
                                // Tennis = tweet court, pas d'analyse IA
                                $coteAt = !empty($bet['cote']) ? ' @' . $bet['cote'] : '';
                                $matchName = trim($bet['titre'] ?? '');
                                $phrases = [
                                    'gagne'  => "🎾 Bet validé{$coteAt} ✅\n\n{$matchName}\n\n📲 stratedgepronos.fr",
                                    'annule' => "🎾 Bet annulé — {$matchName}\n\n📲 stratedgepronos.fr",
                                ];
                            } elseif ($isFun) {
                                // Fun = tweet court, pas d'analyse IA
                                $coteAt = !empty($bet['cote']) ? ' @' . $bet['cote'] : '';
                                $matchName = trim($bet['titre'] ?? '');
                                $phrases = [
                                    'gagne'  => "🎲 Bet Fun validé{$coteAt} ✅\n\n{$matchName}\n\n📲 stratedgepronos.fr",
                                    'annule' => "🎲 Bet Fun annulé — {$matchName}\n\n📲 stratedgepronos.fr",
                                ];
                            } else {
                                // Multi = tweet avec analyse IA courte
                                $tweetExplication = genererTweetExplication($bet, $resultat);

                                if ($tweetExplication !== '') {
                                    $phrases = [
                                        'gagne'  => "✅ BET GAGNÉ{$titre}{$coteStr} ! 🎉\n\n{$tweetExplication}\n\n📲 stratedgepronos.fr",
                                        'annule' => "↺ Bet annulé{$titre} — remboursement.\n\n{$tweetExplication}\n\n📲 stratedgepronos.fr",
                                    ];
                                } else {
                                    $phrases = [
                                        'gagne'  => "✅ BET GAGNÉ{$titre}{$coteStr} ! 🎉\n\nC'est passé comme prévu ! 💰\n\n📲 stratedgepronos.fr",
                                        'annule' => "↺ Bet annulé{$titre} — remboursement.\n\n📲 stratedgepronos.fr",
                                    ];
                                }
                            }
                            $texte = $phrases[$resultat] ?? $phrases['gagne'];

                            // Hashtags selon le role du tipster
                            if (function_exists('hashtagsForRole')) {
                                $texte .= "\n\n" . hashtagsForRole($roleOriginal);
                            }

                            $imgPath = isset($bet['image_path']) ? trim($bet['image_path']) : '';
                            if ($imgPath !== '' && strpos(ltrim($imgPath, '/'), 'uploads/') !== 0) {
                                $imgPath = 'uploads/bets/' . ltrim($imgPath, '/');
                            }
                            $imageUrl = $imgPath !== ''
                                ? 'https://stratedgepronos.fr/restore-image.php?dir=bets&file=' . rawurlencode(basename($imgPath))
                                : 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';
                            $webhookUrl = ($imgPath !== '' && !empty($twitterConfig['webhook_url_image']))
                                ? $twitterConfig['webhook_url_image']
                                : $twitterConfig['webhook_url'];
                            // Même format que nouveau bet / twitter-post : value1=texte, value2=title, value3=imageUrl
                            $payload = json_encode([
                                'value1' => $texte,
                                'value2' => 'StratEdge Pronos',
                                'value3' => $imageUrl,
                            ], JSON_UNESCAPED_UNICODE);
                            $ch = curl_init($webhookUrl);
                            curl_setopt_array($ch, [
                                CURLOPT_POST           => true,
                                CURLOPT_POSTFIELDS     => $payload,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                                CURLOPT_TIMEOUT        => 10,
                            ]);
                            curl_exec($ch);
                            $twCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            $twitterResultMsg = ($twCode >= 200 && $twCode < 300)
                                ? ' 🐦 Tweet résultat envoyé !'
                                : ' (Twitter: HTTP ' . $twCode . ')';
                        }
                    } catch (Throwable $e) {
                        error_log('[poster-bet] set_resultat twitter: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    }

                    $success = 'Résultat enregistré ✅ — Le bet est passé en historique.' . $twitterResultMsg;
                }
            }
        }

        elseif ($_POST['action'] === 'delete_bet') {
            $betId = (int)($_POST['bet_id'] ?? 0);
            $s = $db->prepare("SELECT image_path FROM bets WHERE id = ?"); $s->execute([$betId]);
            $bet = $s->fetch();
            if ($bet && $bet['image_path'] && file_exists(__DIR__ . '/../' . $bet['image_path'])) unlink(__DIR__ . '/../' . $bet['image_path']);
            $db->prepare("DELETE FROM bets WHERE id = ?")->execute([$betId]);
            $success = 'Bet supprimé.';
        }

        elseif ($_POST['action'] === 'toggle_bet') {
            $betId = (int)($_POST['bet_id'] ?? 0);
            $db->prepare("UPDATE bets SET actif = NOT actif WHERE id = ?")->execute([$betId]);
            $success = 'Visibilité modifiée.';
        }
    }
}

if (isset($_GET['posted_from_card']) && $_GET['posted_from_card'] === '1') {
    $success = 'Bet posté depuis Créer une Card ✅';
}

$betsRaw  = $db->query("SELECT * FROM bets ORDER BY date_post DESC")->fetchAll();
$nbDaily  = $db->query("SELECT COUNT(*) FROM abonnements WHERE type='daily' AND actif=1")->fetchColumn();

// Grouper par semaine (lundi–dimanche) pour affichage par dossier/semaine
$betsByWeek = [];
foreach ($betsRaw as $b) {
    $ts = strtotime($b['date_post']);
    $lundi = strtotime('monday this week', $ts);
    if ($lundi === false) $lundi = strtotime('last monday', $ts);
    $cle = date('Y-m-d', $lundi);
    if (!isset($betsByWeek[$cle])) $betsByWeek[$cle] = [];
    $betsByWeek[$cle][] = $b;
}
// Trier les semaines de la plus récente à la plus ancienne
krsort($betsByWeek, SORT_STRING);

$resultatConfig = [
    'en_cours' => ['label'=>'⏳ En cours', 'color'=>'#8a9bb0', 'bg'=>'rgba(255,255,255,0.05)'],
    'gagne'    => ['label'=>'✅ Gagné',    'color'=>'#00c864', 'bg'=>'rgba(0,200,100,0.1)'],
    'perdu'    => ['label'=>'❌ Perdu',    'color'=>'#ff4444', 'bg'=>'rgba(255,68,68,0.1)'],
    'annule'   => ['label'=>'↺ Annulé',  'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,0.1)'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Poster un Bet – Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);--glow-green:0 0 20px rgba(255,45,120,0.3);}
    *{margin:0;padding:0;box-sizing:border-box;}
    html,body{overflow-x:hidden!important;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;}
    .main{padding:2rem;}
    .page-header{margin-bottom:2rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:700;}
    .page-header p{color:var(--text-muted);margin-top:0.3rem;}
    .two-cols{display:grid;grid-template-columns:1fr 1.5fr;gap:2rem;align-items:start;}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
    .card h3{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;margin-bottom:1.5rem;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.2);border-radius:10px;padding:1rem 1.2rem;color:#00c864;margin-bottom:1.5rem;font-size:0.95rem;}
    .alert-error{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);border-radius:10px;padding:1rem 1.2rem;color:#ff6b9d;margin-bottom:1.5rem;font-size:0.95rem;}

    /* DROP ZONE */
    .drop-zone{border:2px dashed rgba(255,45,120,0.3);border-radius:12px;padding:2rem;text-align:center;cursor:pointer;transition:all 0.3s;background:rgba(255,45,120,0.02);}
    .drop-zone:hover,.drop-zone.drag-over{border-color:var(--neon-green);background:rgba(255,45,120,0.06);}
    .drop-zone .icon{font-size:2.5rem;margin-bottom:0.5rem;}
    .drop-zone p{color:var(--text-muted);font-size:0.88rem;}
    .drop-zone strong{color:var(--neon-green);}

    /* PREVIEW GRID */
    .previews-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-top:1.2rem;}
    .preview-item{background:var(--bg-card2);border:1px solid rgba(255,255,255,0.07);border-radius:12px;overflow:hidden;position:relative;transition:all 0.35s;}
    .preview-item.loaded{border-color:rgba(0,200,100,0.45);box-shadow:0 0 12px rgba(0,200,100,0.1);}
    .preview-img{width:100%;height:120px;object-fit:cover;display:block;}
    /* ✓ VERT */
    .check-badge{position:absolute;top:7px;right:7px;width:26px;height:26px;background:#00c864;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.9rem;color:#fff;opacity:0;transform:scale(0.5);transition:all 0.35s;box-shadow:0 0 10px rgba(0,200,100,0.6);}
    .preview-item.loaded .check-badge{opacity:1;transform:scale(1);}
    .remove-btn{position:absolute;top:7px;left:7px;width:22px;height:22px;background:rgba(255,45,120,0.85);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;cursor:pointer;color:white;transition:all 0.2s;}
    .remove-btn:hover{background:var(--neon-green);}
    .preview-body{padding:0.6rem;}
    .preview-fname{font-size:0.75rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:0.4rem;}
    .preview-body input,.preview-body select{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:0.45rem 0.6rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.82rem;outline:none;margin-bottom:0.4rem;transition:border 0.2s;}
    .preview-body input:focus,.preview-body select:focus{border-color:var(--neon-green);}
    .preview-body select option{background:#111827;}

    /* EXPIRE TOGGLE */
    .expire-box{display:flex;align-items:center;gap:0.75rem;background:rgba(255,200,0,0.05);border:1px solid rgba(255,200,0,0.15);border-radius:10px;padding:0.9rem 1rem;margin-top:1.2rem;cursor:pointer;}
    .expire-box input[type=checkbox]{width:18px;height:18px;accent-color:var(--neon-green);cursor:pointer;flex-shrink:0;}
    .expire-box .elabel{font-size:0.88rem;color:#b0a060;line-height:1.5;}
    .expire-box .elabel strong{color:#ffc107;}
    .expire-count{background:#ffc107;color:#050810;font-size:0.68rem;font-weight:700;padding:0.1rem 0.5rem;border-radius:10px;margin-left:auto;white-space:nowrap;}

    .locked-upload-zone{margin-top:0.6rem;border:1.5px dashed rgba(255,45,120,0.35);border-radius:8px;padding:0.6rem 0.8rem;cursor:pointer;transition:all 0.2s;background:rgba(255,45,120,0.04);}
    .locked-upload-zone:hover{border-color:rgba(255,45,120,0.7);background:rgba(255,45,120,0.08);}
    .locked-upload-zone.has-file{border-color:#ff2d78;background:rgba(255,45,120,0.1);}
    .locked-label{font-size:0.82rem;font-weight:700;color:#ff6b9d;margin-bottom:0.2rem;}
    .locked-hint{font-size:0.75rem;color:var(--text-muted);}
    .locked-preview-img{width:100%;height:60px;object-fit:cover;border-radius:5px;margin-top:0.4rem;}

    .btn-submit{width:100%;background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:white;padding:1rem;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:1.05rem;font-weight:700;text-transform:uppercase;cursor:pointer;transition:all 0.3s;margin-top:1.2rem;}
    .btn-submit:hover{box-shadow:var(--glow-green);}
    .btn-submit:disabled{opacity:0.4;cursor:not-allowed;}

    /* TABLE */
    table{width:100%;border-collapse:collapse;}
    th{text-align:left;font-family:'Space Mono',monospace;font-size:0.62rem;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);padding:0.7rem;border-bottom:1px solid rgba(255,255,255,0.05);}
    td{padding:0.75rem 0.7rem;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--text-secondary);font-size:0.84rem;vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    .bet-thumb{width:55px;height:38px;object-fit:cover;border-radius:6px;display:block;}
    .bet-thumb-wrap{position:relative;width:55px;height:38px;flex-shrink:0;}
    .bet-thumb-placeholder{align-items:center;justify-content:center;width:55px;height:38px;background:rgba(255,255,255,0.06);border-radius:6px;font-size:1.1rem;}
    .week-block.collapsed .week-body-row{display:none;}
    .week-header{background:rgba(255,45,120,0.08);border-left:3px solid var(--neon-green);font-family:'Orbitron',sans-serif;font-size:0.75rem;font-weight:700;color:var(--text-primary);padding:0.5rem 0.7rem;cursor:pointer;user-select:none;}
    .week-header:hover{background:rgba(255,45,120,0.12);}
    .week-header td{padding:0.5rem 0.7rem!important;border-bottom:1px solid rgba(255,45,120,0.15);}
    .week-header .week-toggle{display:inline-block;transition:transform .2s;margin-right:6px;}
    .week-block:not(.collapsed) .week-toggle{transform:rotate(90deg);}
    .btn-sm{padding:0.3rem 0.7rem;border-radius:6px;font-family:'Rajdhani',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;border:none;transition:all 0.2s;}
    .btn-danger{background:rgba(255,45,120,0.1);color:#ff6b9d;border:1px solid rgba(255,45,120,0.2);}
    .btn-toggle{background:rgba(255,255,255,0.06);color:var(--text-secondary);border:1px solid rgba(255,255,255,0.1);}
    .no-bets{text-align:center;color:var(--text-muted);padding:2rem;}

    @media(max-width:768px){
      html,body{overflow-x:hidden!important;width:100%!important;}
      .main{margin-left:0!important;width:100%!important;max-width:100vw!important;min-width:0!important;overflow-x:hidden;padding:0.8rem!important;padding-top:62px!important;padding-bottom:calc(78px + env(safe-area-inset-bottom,0px))!important;box-sizing:border-box!important;}
      .two-cols{grid-template-columns:1fr!important;gap:1rem;}
      .page-header h1{font-size:1.15rem;}
      .page-header p{font-size:0.82rem;word-wrap:break-word;}
      .previews-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:0.7rem;}
      .preview-img{height:90px;}
      .preview-body{padding:0.5rem;}
      .preview-body input,.preview-body select{font-size:0.8rem;padding:0.4rem 0.5rem;margin-bottom:0.3rem;}
      .card{padding:0.9rem;border-radius:10px;margin-bottom:1rem;max-width:100%!important;overflow:hidden;}
      .card h3{font-size:0.82rem;margin-bottom:1rem;}
      .btn-submit{font-size:0.92rem;padding:0.85rem;min-height:48px;white-space:normal;word-wrap:break-word;text-align:center;}
      .table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:8px;}
      .table-wrap table{min-width:700px;}
      .drop-zone{padding:1.2rem;word-wrap:break-word;}
      .drop-zone .icon{font-size:2rem;}
      .drop-zone p{font-size:0.82rem;word-wrap:break-word;}
      .expire-box{padding:0.7rem 0.8rem;flex-wrap:wrap;gap:0.5rem;}
      .expire-box .elabel{font-size:0.82rem;word-wrap:break-word;}
      .expire-count{font-size:0.62rem;}
      .locked-upload-zone{padding:0.5rem 0.6rem;}
      .locked-label{font-size:0.75rem;}
      .locked-hint{font-size:0.7rem;}
      .week-header td{font-size:0.72rem!important;padding:0.4rem 0.5rem!important;}
      td{font-size:0.78rem;padding:0.5rem 0.4rem;}
      th{font-size:0.55rem;padding:0.4rem 0.3rem;}
      .bet-thumb{width:42px;height:30px;}
      .bet-thumb-wrap{width:42px;height:30px;}
      .btn-sm{padding:0.25rem 0.5rem;font-size:0.75rem;min-height:34px;min-width:34px;}
      .alert-success,.alert-error{font-size:0.88rem;padding:0.8rem;border-radius:8px;}
    }
    @media(max-width:400px){
      .main{padding:0.5rem!important;padding-top:58px!important;padding-bottom:calc(72px + env(safe-area-inset-bottom,0px))!important;}
      .previews-grid{grid-template-columns:1fr 1fr;gap:0.5rem;}
      .preview-img{height:70px;}
      .preview-body input,.preview-body select{font-size:0.75rem;padding:0.35rem 0.45rem;}
      .page-header h1{font-size:1rem;}
      .card{padding:0.7rem;}
      .btn-submit{font-size:0.88rem;padding:0.75rem;}
      .drop-zone p{font-size:0.78rem;}
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <div class="page-header">
    <h1>📸 Poster des Bets</h1>
    <p>Sélectionnez une ou plusieurs images, configurez chaque bet puis validez en une fois</p>
  </div>

  <?php if ($success): ?><div class="alert-success">✅ <?= clean($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error">⚠️ <?= clean($error) ?></div><?php endif; ?>

  <div class="two-cols">

    <!-- FORMULAIRE -->
    <div class="card">
      <h3>📤 Upload</h3>
      <form method="POST" enctype="multipart/form-data" id="betForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="post_bets">
        <input type="file" id="fileInput" name="images[]" accept="image/*" multiple style="display:none">

        <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
          <div class="icon">📁</div>
          <p>Cliquez ou <strong>glissez</strong> vos images ici</p>
          <p style="margin-top:0.3rem;font-size:0.78rem;">JPG · PNG · WEBP — max 10 Mo · plusieurs fichiers OK</p>
        </div>

        <div class="previews-grid" id="previewsGrid"></div>

        <label class="expire-box">
          <input type="checkbox" name="expire_daily" id="expireDaily">
          <div class="elabel">
            <strong>⚠️ Expirer les abonnements Daily</strong><br>
            Cochez seulement pour un nouveau bet principal (Safe/Live). <em>Ne pas cocher</em> si c'est un bet Fun bonus.
          </div>
          <?php if ($nbDaily > 0): ?>
            <span class="expire-count"><?= $nbDaily ?> Daily actif<?= $nbDaily > 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </label>

        <button type="submit" class="btn-submit" id="submitBtn" disabled>
          📸 Poster les bets →
        </button>
      </form>
    </div>

    <!-- LISTE BETS -->
    <div class="card">
      <h3>Bets publiés (<?= count($betsRaw) ?>)</h3>
      <?php if (empty($betsByWeek)): ?>
        <div class="no-bets">Aucun bet pour le moment.</div>
      <?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Image</th><th>Titre</th><th>Type</th><th>Catégorie</th><th>Date</th><th>Résultat</th><th>Visible</th><th></th></tr></thead>
          <?php
            $currentWeekKey = date('Y-m-d', strtotime('monday this week'));
          ?>
          <?php foreach ($betsByWeek as $weekKey => $weekBets):
            $lundiTs = strtotime($weekKey);
            $dimancheTs = strtotime('+6 days', $lundiTs);
            $weekLabel = 'Semaine du ' . date('d/m', $lundiTs) . ' → ' . date('d/m/Y', $dimancheTs);
            $isCurrentWeek = ($weekKey === $currentWeekKey);
          ?>
          <tbody class="week-block<?= $isCurrentWeek ? '' : ' collapsed' ?>" data-week="<?= htmlspecialchars($weekKey) ?>">
            <tr class="week-header" role="button" tabindex="0" aria-expanded="<?= $isCurrentWeek ? 'true' : 'false' ?>" title="Cliquer pour déplier/replier">
              <td colspan="8"><span class="week-toggle">▶</span> <?= $weekLabel ?> (<?= count($weekBets) ?> bet<?= count($weekBets) > 1 ? 's' : '' ?>)</td>
            </tr>
          <?php foreach ($weekBets as $b):
            $resKey = isset($b['resultat']) ? (is_string($b['resultat']) ? strtolower(trim($b['resultat'])) : 'en_cours') : 'en_cours';
            if (!isset($resultatConfig[$resKey])) $resKey = 'en_cours';
            $rc = $resultatConfig[$resKey];
            // Image : priorité image_path (bets), sinon locked_image_path (locked) — chemins canoniques
            $imgSrc = '';
            if (!empty($b['image_path'])) $imgSrc = betImageUrl(trim($b['image_path']), 'bets');
            if ($imgSrc === '' && !empty($b['locked_image_path'])) $imgSrc = betImageUrl(trim($b['locked_image_path']), 'locked');
          ?>
            <tr class="week-body-row">
              <td><?php if ($imgSrc !== ''): ?><img <?= $isCurrentWeek ? 'src' : 'data-src' ?>="<?= htmlspecialchars($imgSrc) ?>" class="bet-thumb" alt="" loading="lazy" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='inline-flex';"><span class="bet-thumb-placeholder" style="display:none;">📊</span><?php else: ?><span class="bet-thumb-placeholder" style="display:inline-flex;">📊</span><?php endif; ?></td>
              <td style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($b['titre'] ?: '—') ?></td>
              <td style="font-size:0.8rem;"><?= clean($b['type']) ?></td>
              <td>
                <?php if(($b['categorie']??'multi')==='tennis'): ?>
                  <span style="background:rgba(0,212,106,0.12);color:#00d46a;border:1px solid rgba(0,212,106,0.3);padding:0.15rem 0.6rem;border-radius:6px;font-size:0.72rem;font-weight:700;">🎾 Tennis</span>
                <?php else: ?>
                  <span style="background:rgba(0,212,255,0.1);color:#00d4ff;border:1px solid rgba(0,212,255,0.25);padding:0.15rem 0.6rem;border-radius:6px;font-size:0.72rem;font-weight:700;">⚽ Multi</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.78rem;white-space:nowrap;"><?= date('d/m H:i', strtotime($b['date_post'])) ?></td>

              <!-- RÉSULTAT : toujours modifiable (même pour les anciens bets) -->
              <td>
                <?php $currentResult = $resKey; ?>
                <?php if ($currentResult !== 'en_cours'): ?>
                  <span style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;padding:0.25rem 0.5rem;border-radius:6px;font-size:0.78rem;font-weight:700;margin-right:0.35rem;"><?= $rc['label'] ?></span>
                <?php endif; ?>
                <span style="font-size:0.7rem;color:var(--text-muted);margin-right:0.25rem;">Modifier :</span>
                <div style="display:inline-flex;gap:0.25rem;flex-wrap:wrap;align-items:center;">
                  <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $currentResult === 'gagne' ? 'Déjà gagné. Changer quand même ?' : 'Marquer comme GAGNÉ ?' ?>')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="set_resultat">
                    <input type="hidden" name="bet_id" value="<?= $b['id'] ?>">
                    <input type="hidden" name="resultat" value="gagne">
                    <button type="submit" class="btn-sm" style="background:rgba(0,200,100,0.15);color:#00c864;border:1px solid rgba(0,200,100,0.3);" title="Gagné">✅</button>
                  </form>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $currentResult === 'perdu' ? 'Déjà perdu. Changer quand même ?' : 'Marquer comme PERDU ?' ?>')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="set_resultat">
                    <input type="hidden" name="bet_id" value="<?= $b['id'] ?>">
                    <input type="hidden" name="resultat" value="perdu">
                    <button type="submit" class="btn-sm" style="background:rgba(255,68,68,0.15);color:#ff4444;border:1px solid rgba(255,68,68,0.3);" title="Perdu">❌</button>
                  </form>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $currentResult === 'annule' ? 'Déjà annulé. Changer quand même ?' : 'Marquer comme ANNULÉ ?' ?>')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="set_resultat">
                    <input type="hidden" name="bet_id" value="<?= $b['id'] ?>">
                    <input type="hidden" name="resultat" value="annule">
                    <button type="submit" class="btn-sm" style="background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);" title="Annulé">↺</button>
                  </form>
                </div>
              </td>

              <td>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="toggle_bet">
                  <input type="hidden" name="bet_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn-sm btn-toggle"><?= $b['actif'] ? '✅' : '❌' ?></button>
                </form>
              </td>
              <td>
                <form method="POST" onsubmit="return confirm('Supprimer ce bet ?')">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="delete_bet">
                  <input type="hidden" name="bet_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn-sm btn-danger">🗑</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
const fileInput = document.getElementById('fileInput');
const grid      = document.getElementById('previewsGrid');
const submitBtn = document.getElementById('submitBtn');
const dropZone  = document.getElementById('dropZone');
let dt = new DataTransfer();
let fileIndex = 0; // compteur unique par fichier

const typeOpts = `
<?php if ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport'): ?>
  <option value="safe">🛡️ Safe</option>
  <option value="fun" selected>🎯 Fun</option>
  <option value="live">⚡ Live</option>
  <option value="safe_combi">🛡️⚡ Combi</option>
<?php elseif ($adminRole === 'admin_tennis'): ?>
  <option value="safe">🛡️ Safe</option>
  <option value="fun">🎯 Fun</option>
  <option value="live">⚡ Live</option>
  <option value="safe_combi">🛡️⚡ Combi</option>
<?php else: ?>
  <option value="safe">🛡️ Safe</option>
  <option value="fun">🎯 Fun</option>
  <option value="live">⚡ Live</option>
  <option value="safe_combi">🛡️⚡ Safe Combiné</option>
  <option value="safe,fun">🛡️+🎯 Safe+Fun</option>
  <option value="safe,live">🛡️+⚡ Safe+Live</option>
<?php endif; ?>`;

function fmtSize(b) {
  return b > 1048576 ? (b/1048576).toFixed(1)+' Mo' : Math.round(b/1024)+' Ko';
}

function addFiles(files) {
  Array.from(files).forEach(file => {
    if (Array.from(dt.files).some(f => f.name===file.name && f.size===file.size)) return;
    dt.items.add(file);

    const idx  = fileIndex++;
    const item = document.createElement('div');
    item.className  = 'preview-item';
    item.dataset.idx = idx;

    const reader = new FileReader();
    reader.onload = e => {
      item.innerHTML = `
        <img src="${e.target.result}" class="preview-img">
        <div class="check-badge">✓</div>
        <button type="button" class="remove-btn" onclick="removeFile(${idx}, '${file.name}', ${file.size})">✕</button>
        <div class="preview-body">
          <div class="preview-fname" title="${file.name}">${file.name} · ${fmtSize(file.size)}</div>
          <input type="text" name="titre[${idx}]" placeholder="Titre (optionnel)">
          <select name="type[${idx}]">${typeOpts}</select>
          <select name="categorie[${idx}]" style="margin-top:0.4rem;width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 0.75rem;color:#f0f4f8;font-family:'Rajdhani',sans-serif;font-size:0.9rem;">
            <?php if ($adminRole === 'admin_tennis'): ?>
            <option value="tennis" selected>🎾 Tennis</option>
            <?php elseif ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport'): ?>
            <option value="multi" selected>🎯 Fun</option>
            <?php else: ?>
            <option value="multi">⚽🏀🏒 Multi-sport</option>
            <option value="tennis">🎾 Tennis</option>
            <?php endif; ?>
          </select>
          <?php if ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport'): ?>
          <select name="sport[${idx}]" class="sport-select" style="margin-top:0.4rem;width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 0.75rem;color:#f0f4f8;font-family:'Rajdhani',sans-serif;font-size:0.9rem;">
            <option value="football">⚽ Foot</option>
            <option value="basket">🏀 NBA</option>
            <option value="hockey">🏒 NHL</option>
            <option value="baseball">⚾ MLB</option>
          </select>
          <?php elseif ($adminRole !== 'admin_tennis'): ?>
          <select name="sport[${idx}]" class="sport-select" style="margin-top:0.4rem;width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 0.75rem;color:#f0f4f8;font-family:'Rajdhani',sans-serif;font-size:0.9rem;">
            <option value="football">⚽ Foot</option>
            <option value="tennis">🎾 Tennis</option>
            <option value="basket">🏀 Basket</option>
            <option value="hockey">🏒 Hockey</option>
            <option value="baseball">⚾ MLB</option>
          </select>
          <?php endif; ?>
          <input type="number" name="cote[${idx}]" step="0.01" min="0" placeholder="Cote (optionnel)" style="margin-top:0.4rem;width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 0.75rem;color:#f0f4f8;font-size:0.9rem;">
          <textarea name="analyse_html[${idx}]" placeholder="Analyse HTML (optionnel — coller le HTML de la card pour la page bet)" rows="2" style="margin-top:0.4rem;width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 0.75rem;color:#f0f4f8;font-size:0.75rem;resize:vertical;"></textarea>
          <div class="locked-upload-zone" onclick="this.querySelector('input').click()" title="Card Locked pour Twitter (optionnel)">
            <input type="file" name="locked_images[${idx}]" accept="image/*" style="display:none" onchange="previewLocked(this)">
            <div class="locked-label">🔒 Card Locked Twitter <span style="opacity:0.5;font-size:0.75rem;">(optionnel)</span></div>
            <div class="locked-hint">Clique pour uploader la version floutée</div>
          </div>
        </div>`;
      requestAnimationFrame(() => requestAnimationFrame(() => item.classList.add('loaded')));
    };
    reader.readAsDataURL(file);
    grid.appendChild(item);
  });
  sync();
}

function removeFile(idx, name, size) {
  // Retirer du DataTransfer
  const newDt = new DataTransfer();
  Array.from(dt.files).forEach(f => {
    if (!(f.name === name && f.size === size)) newDt.items.add(f);
  });
  dt = newDt;
  // Retirer du DOM via l'index unique
  const el = document.querySelector(`.preview-item[data-idx="${idx}"]`);
  if (el) el.remove();
  sync();
}

function sync() {
  fileInput.files = dt.files;
  const n = dt.files.length;
  submitBtn.disabled = n === 0;
  submitBtn.textContent = n === 0 ? '📸 Poster les bets →' : `📸 Poster ${n} bet${n>1?'s':''} →`;
}

fileInput.addEventListener('change', () => addFiles(fileInput.files));

function previewLocked(input) {
  const zone = input.closest('.locked-upload-zone');
  const file  = input.files[0];
  if (!file) return;
  zone.classList.add('has-file');
  zone.querySelector('.locked-hint').textContent = '✅ ' + file.name;
  // Mini preview
  const existing = zone.querySelector('.locked-preview-img');
  if (existing) existing.remove();
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.createElement('img');
    img.className = 'locked-preview-img';
    img.src = e.target.result;
    zone.appendChild(img);
  };
  reader.readAsDataURL(file);
}
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); addFiles(e.dataTransfer.files); });

// ── Réindexation séquentielle au submit ──────────────────────
// Le DataTransfer envoie images[0], images[1], images[2]...
// Les inputs titre/type/categorie/locked doivent correspondre au même index
document.getElementById('betForm').addEventListener('submit', function() {
  const items = grid.querySelectorAll('.preview-item');
  items.forEach((item, seq) => {
    const locked = item.querySelector('input[type="file"][name^="locked_images"]');
    if (locked) locked.name = 'locked_images[' + seq + ']';
    const titre = item.querySelector('input[name^="titre"]');
    if (titre) titre.name = 'titre[' + seq + ']';
    const type = item.querySelector('select[name^="type"]');
    if (type) type.name = 'type[' + seq + ']';
    const cat = item.querySelector('select[name^="categorie"]');
    if (cat) cat.name = 'categorie[' + seq + ']';
    const sportSel = item.querySelector('select[name^="sport"]');
    if (sportSel) sportSel.name = 'sport[' + seq + ']';
  });
});

// Replier / déplier les dossiers par semaine + lazy-load images
function lazyLoadWeekImages(tbody) {
  tbody.querySelectorAll('img[data-src]').forEach(function(img) {
    img.src = img.getAttribute('data-src');
    img.removeAttribute('data-src');
  });
}
document.querySelectorAll('.week-header').forEach(function(h) {
  h.addEventListener('click', function() {
    var tbody = this.closest('tbody.week-block');
    if (tbody) {
      var wasCollapsed = tbody.classList.contains('collapsed');
      tbody.classList.toggle('collapsed');
      this.setAttribute('aria-expanded', wasCollapsed ? 'true' : 'false');
      if (wasCollapsed) lazyLoadWeekImages(tbody);
    }
  });
  h.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      this.click();
    }
  });
});


</script>
</body>
</html>
