<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Méthode non autorisée.'); }
if (!verifyCsrf((string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Session expirée. Rechargez la page.'); }
$redirect = 'import.php';
try {
    if ($labError) { throw new RuntimeException($labError); }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'upload') {
        $tables = [];
        foreach (['stats', 'odds'] as $role) {
            $file = $_FILES[$role] ?? null;
            if ($role === 'odds' && (!$file || $file['error'] === UPLOAD_ERR_NO_FILE)) { continue; }
            if (!$file || !is_scalar($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                throw new InvalidArgumentException('Le fichier ' . $role . ' n’a pas été reçu. Vérifiez sa taille (2 Mo maximum).');
            }
            if ($file['size'] > 2097152 || strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'csv') { throw new InvalidArgumentException('Fichier CSV attendu, limité à 2 Mo.'); }
            $tables[$role] = \StratEdgeLab\Engine::csv(file_get_contents($file['tmp_name']), (string)($_POST['separator'][$role] ?? 'auto'), isset($_POST['header'][$role]));
            $tables[$role]['name'] = substr(basename((string)$file['name']), 0, 180);
            $tables[$role]['sha256'] = hash_file('sha256', $file['tmp_name']);
        }
        $id = $labStore->create('draft', ['tables' => $tables], $labOwner);
        $redirect = 'import.php?id=' . $id;
    } elseif ($action === 'analyze') {
        $id = (string)($_POST['id'] ?? '');
        $draft = $labStore->get($id, $labOwner);
        $redirect = 'import.php?id=' . $id;
        if ($draft['kind'] !== 'draft' || !isset($_POST['confirm_mapping'])) { throw new InvalidArgumentException('Vérifiez puis confirmez la correspondance des colonnes.'); }
        $maps = $_POST['mapping'] ?? [];
        $options = $_POST['options'] ?? [];
        if (!is_array($maps) || !is_array($options)) { throw new InvalidArgumentException('Configuration invalide.'); }
        if (!isset($options['markets'])) { throw new InvalidArgumentException('Sélectionnez au moins un marché à comparer.'); }
        $result = \StratEdgeLab\Engine::analyze($draft['data']['tables'], $maps, $options);
        if (!$result['matches']) {
            $_SESSION['lab_import_errors'] = array_slice($result['errors'], 0, 100);
            $_SESSION['lab_form'] = ['id' => $id, 'maps' => $maps, 'options' => $options];
            throw new InvalidArgumentException('Aucun match analysable. Consultez les erreurs ci-dessous ; vos choix de colonnes sont conservés.');
        }
        $id = $labStore->create('analysis', ['analysis' => $result, 'maps' => $maps, 'tables' => $draft['data']['tables'], 'draft_id' => $id], $labOwner);
        unset($_SESSION['lab_form'], $_SESSION['lab_import_errors']);
        $redirect = 'index.php?id=' . $id;
    } elseif (in_array($action, ['context', 'research', 'result'], true)) {
        $id = (string)($_POST['id'] ?? ''); $key = (string)($_POST['match'] ?? '');
        $run = $labStore->get($id, $labOwner); $match = lab_find_match($run, $key);
        $redirect = 'match.php?id=' . $id . '&match=' . $key;
        if ($action === 'context') {
            $decision = (string)($_POST['decision'] ?? '');
            if (!in_array($decision, ['pending', 'retained', 'excluded'], true)) { throw new InvalidArgumentException('Décision invalide.'); }
            if (new DateTimeImmutable($match['kickoff']) <= new DateTimeImmutable('now')) { throw new InvalidArgumentException('Les décisions prématch sont figées après le coup d’envoi.'); }
            $note = trim((string)($_POST['note'] ?? ''));
            $source = trim((string)($_POST['source'] ?? ''));
            if (strlen($note) > 6000 || strlen($source) > 2000 || ($source !== '' && !lab_url($source))) { throw new InvalidArgumentException('Note ou lien source invalide.'); }
            if ($decision !== 'pending' && $note === '') { throw new InvalidArgumentException('Indiquez la raison de votre décision.'); }
            $labStore->event($id, $key, 'context', ['decision' => $decision, 'note' => $note, 'source' => $source], $labOwner);
        } elseif ($action === 'research') {
            $last = lab_latest($labStore->events($id, $labOwner));
            if (isset($last[$key]['research']) && strtotime($last[$key]['research']['created_at']) > time() - 600) { throw new InvalidArgumentException('Une recherche récente est déjà disponible. Attendez dix minutes avant de l’actualiser.'); }
            if (($_SESSION['lab_research_at'] ?? 0) > time() - 30) { throw new InvalidArgumentException('Attendez quelques secondes avant une nouvelle recherche.'); }
            $_SESSION['lab_research_at'] = time();
            session_write_close();
            $research = \StratEdgeLab\Context::request($match);
            $labStore->event($id, $key, 'research', $research, $labOwner);
            session_start();
        } else {
            if (!$match['pick']) { throw new InvalidArgumentException('Aucun pari à régler.'); }
            $earliest = (new DateTimeImmutable($match['kickoff']))->modify('+45 minutes');
            if (new DateTimeImmutable('now') < $earliest) { throw new InvalidArgumentException('Attendez la fin de la période concernée avant de saisir le score.'); }
            $source = trim((string)($_POST['result_source'] ?? ''));
            if (strlen($source) > 2000 || ($source !== '' && !lab_url($source))) { throw new InvalidArgumentException('Lien du résultat invalide.'); }
            $note = trim((string)($_POST['result_note'] ?? ''));
            if (strlen($note) > 2000 || !isset($_POST['confirm_result'])) { throw new InvalidArgumentException('Confirmez que la période est terminée et le score vérifié.'); }
            if (isset($_POST['void'])) { $data = ['outcome' => 'void', 'source' => $source, 'note' => $note]; }
            else {
                $h = filter_var($_POST['score_home'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
                $a = filter_var($_POST['score_away'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
                if ($h === false || $a === false) { throw new InvalidArgumentException('Saisissez les deux scores de la période concernée.'); }
                $data = ['outcome' => \StratEdgeLab\Engine::settle($match['pick'], $h, $a) ? 'won' : 'lost', 'home' => $h, 'away' => $a, 'period' => $match['pick']['period'], 'source' => $source, 'note' => $note];
            }
            $labStore->event($id, $key, 'result', $data, $labOwner);
        }
        $_SESSION['lab_success'] = 'Enregistré. Les prévisions initiales restent inchangées.';
    } else { throw new InvalidArgumentException('Action inconnue.'); }
} catch (Throwable $e) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $_SESSION['lab_error'] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'L’opération a échoué. Vérifiez le fichier ou la connexion au stockage.';
    error_log('StratEdge Lab: action failed (' . get_class($e) . ')');
}
header('Location: ' . $labBase . $redirect, true, 303);
exit;
