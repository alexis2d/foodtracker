<?php

/**
 * Deploy-only cache buster. Deliberately NOT routed through Symfony:
 *
 * - Deploys upload source over FTP but never touch var/ (excluded in
 *   deploy.yml), so the compiled container + router cache from the
 *   previous deploy would otherwise keep being served forever, hiding any
 *   newly added route/service/controller behind 404s.
 * - Clearing it from *inside* a Symfony request breaks that same request:
 *   the container lazily requires per-service PHP files from the cache
 *   directory on demand, so deleting it mid-request makes any
 *   not-yet-loaded service explode.
 * - A brand new Symfony route added specifically to do this clearing would
 *   itself be invisible until the stale cache is cleared — chicken and egg.
 *
 * .htaccess serves any file that physically exists without rewriting to
 * index.php (`RewriteCond %{REQUEST_FILENAME} -f`), so this script is
 * reachable the instant it lands on disk via FTP, with no dependency on
 * Symfony's cache at all. It only deletes the cache directory; the *next*
 * request (e.g. the /api/internal/migrate call right after this one) boots
 * a genuinely fresh kernel that rebuilds it cleanly as part of normal boot.
 */

require dirname(__DIR__).'/vendor/autoload.php';

(new \Symfony\Component\Dotenv\Dotenv())->usePutenv()->loadEnv(dirname(__DIR__).'/.env');

header('Content-Type: application/json');

$providedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
$expectedToken = $_SERVER['DEPLOY_TOKEN'] ?? '';

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

if ('' === $expectedToken || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$appEnv = $_SERVER['APP_ENV'] ?? 'dev';
$cacheDir = dirname(__DIR__).'/var/cache/'.$appEnv;

$removeDir = static function (string $dir) use (&$removeDir): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }
        $path = $dir.'/'.$item;
        is_dir($path) ? $removeDir($path) : @unlink($path);
    }
    @rmdir($dir);
};

$removeDir($cacheDir);

echo json_encode(['success' => true, 'cacheDir' => $cacheDir]);
