<?php
/**
 * STRATEDGE — Edge Finder — Helpers communs workers (v2.2)
 * =========================================================
 * 1. se_cli_bootstrap()      : permet de lancer worker/writer en CLI
 *    (php fichier.php match_id=N worker_token=X) -> args copiés dans $_GET.
 *    En CLI, AUCUNE limite nginx/PHP-FPM (le fameux couperet ~122s disparaît).
 * 2. se_anthropic_call()     : appel API Anthropic robuste — timeout long,
 *    connect-timeout séparé, retry x3 avec backoff sur erreurs transitoires
 *    (HTTP 0/timeout, 408, 429 rate-limit, 5xx, 529 overloaded).
 * 3. se_spawn_background()   : lance un script worker en CLI tâche de fond.
 *    Retourne false si exec() indisponible -> l'appelant garde le repli HTTP.
 */

declare(strict_types=1);

function se_cli_bootstrap(): void
{
    if (php_sapi_name() !== 'cli') {
        return;
    }
    global $argv;
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $_GET[$k] = $v;
        }
    }
}

/**
 * @return array{0:?string,1:int,2:string} [$response, $http_code, $curl_err]
 */
function se_anthropic_call(array $payload, int $timeout = 600, int $attempts = 3): array
{
    $last_err = '';
    $last_code = 0;
    for ($try = 1; $try <= $attempts; $try++) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code === 200 && $response) {
            return [$response, $code, ''];
        }
        $last_err = $err !== '' ? $err : (string)$response;
        $last_code = $code;

        $retryable = ($code === 0 || $code === 408 || $code === 429 || $code >= 500);
        if (!$retryable || $try === $attempts) {
            break;
        }
        sleep(min(15, 4 * $try)); // backoff 4s, 8s
    }
    return [null, $last_code, $last_err];
}

/**
 * Lance $script (chemin absolu) en CLI tâche de fond avec match_id + token.
 * @return bool true si lancé en CLI, false si exec indisponible (repli HTTP).
 */
function se_spawn_background(string $script, int $match_id): bool
{
    if (!function_exists('exec')) {
        return false;
    }
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (in_array('exec', $disabled, true)) {
        return false;
    }
    $php = '/usr/bin/php';
    if (!is_file($php)) {
        $candidate = PHP_BINDIR . '/php';
        $php = is_file($candidate) ? $candidate : 'php';
    }
    $cmd = sprintf(
        'nohup %s %s match_id=%d worker_token=%s > /dev/null 2>&1 &',
        escapeshellcmd($php),
        escapeshellarg($script),
        $match_id,
        escapeshellarg(SE_WORKER_TOKEN)
    );
    exec($cmd);
    return true;
}
