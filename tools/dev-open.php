<?php
/**
 * ローカル開発環境を立ち上げてブラウザで開く。PhpStorm の実行構成「開発サーバー」から呼ばれる。
 *
 * - APP_URL が応答しなければ Laragon を起動して待つ
 * - Laragon が無い環境 (Linux 等) では php artisan dev (serve + queue + Vite) に切り替える
 * - Vite が止まっていれば npm run dev を子プロセスで動かし、ログを実行ウィンドウに流す
 * - 準備ができたら既定のブラウザで開く
 *
 * すでに Vite が動いている場合はブラウザを開くだけで終わる。
 */

declare(strict_types=1);

const VITE_PORT = 5173;
const SERVE_URL = 'http://127.0.0.1:8000';
const TIMEOUT_SEC = 60;

$root = dirname(__DIR__);
chdir($root);

/**
 * .env から APP_URL を読む。
 */
function appUrl(string $root): string {
    $env = file_get_contents($root . '/.env');
    if ($env === false || !preg_match('/^APP_URL=(.+)$/m', $env, $m)) throw new RuntimeException('.env に APP_URL がありません');
    return trim($m[1], " \t\r\"");
}

/**
 * URL が応答するか。404 や 500 でもサーバー自体は生きているので真とする。
 */
function urlResponds(string $url): bool {
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0]]);
    return @file_get_contents($url, false, $ctx, 0, 1) !== false;
}

/**
 * 指定ポートを LISTEN しているプロセスがあるか。
 */
function portOpen(int $port): bool {
    // Node は localhost を ::1 だけで待ち受けることがあるので IPv4/IPv6 の両方を見る
    foreach (['127.0.0.1', '[::1]'] as $host) {
        $sock = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($sock === false) continue;
        fclose($sock);
        return true;
    }
    return false;
}

/**
 * 条件が真になるまで待つ。
 *
 * @param callable(): bool $cond
 */
function waitUntil(callable $cond, string $what): void {
    $deadline = time() + TIMEOUT_SEC;
    while (!$cond()) {
        if (time() > $deadline) throw new RuntimeException("{$what} が " . TIMEOUT_SEC . ' 秒以内に起動しませんでした');
        usleep(500_000);
    }
}

/**
 * Laragon の実行ファイル。Laragon 配下 (laragon/www/<project>) に置かれていなければ null。
 */
function laragonExe(string $root): ?string {
    $exe = dirname($root, 2) . DIRECTORY_SEPARATOR . 'laragon.exe';
    return PHP_OS_FAMILY === 'Windows' && is_file($exe) ? $exe : null;
}

/**
 * Laragon が止まっていれば起動して APP_URL の応答を待つ。
 */
function ensureLaragon(string $laragon, string $url): void {
    if (urlResponds($url)) {
        echo "[ok] {$url} は起動済み\n";
        return;
    }
    echo "[..] Laragon を起動します (自動で始まらない場合は Laragon の「全て開始」を押してください)\n";
    pclose(popen('start "" ' . escapeshellarg($laragon), 'r'));
    waitUntil(fn(): bool => urlResponds($url), 'Laragon');
    echo "[ok] {$url} が応答しました\n";
}

/**
 * Vite を子プロセスで起動し、hot ファイルができるまで待つ。
 *
 * @return resource
 */
function startVite(string $root) {
    $hot = $root . '/public/hot';
    // 前回異常終了した hot が残っていると、止まった Vite を参照してしまう
    if (is_file($hot)) unlink($hot);
    echo "[..] Vite を起動します\n";
    $proc = proc_open('npm run dev', [STDIN, STDOUT, STDERR], $pipes, $root);
    if ($proc === false) throw new RuntimeException('npm run dev を起動できませんでした');
    waitUntil(fn(): bool => is_file($hot), 'Vite');
    return $proc;
}

/**
 * 既定のブラウザで URL を開く。
 */
function openBrowser(string $url): void {
    $cmd = match (PHP_OS_FAMILY) {
        'Windows' => 'start "" ' . escapeshellarg($url),
        'Darwin' => 'open ' . escapeshellarg($url),
        default => 'xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &',
    };
    pclose(popen($cmd, 'r'));
}

/**
 * php artisan dev (serve + queue + Vite) を子プロセスで動かし、serve の応答を待ってブラウザで開く。
 */
function runArtisanDev(string $root, string $appUrl): int {
    // issuer やリダイレクト先は APP_URL から作られるので、ずれていると OIDC のフローが通らない
    if (rtrim($appUrl, '/') !== SERVE_URL) echo "[!!] APP_URL ({$appUrl}) が " . SERVE_URL . " と違います。.env の APP_URL を合わせてください\n";
    echo "[..] Laragon が無いので php artisan dev を起動します\n";
    $proc = proc_open([PHP_BINARY, 'artisan', 'dev', '--inline'], [STDIN, STDOUT, STDERR], $pipes, $root);
    if ($proc === false) throw new RuntimeException('php artisan dev を起動できませんでした');
    waitUntil(fn(): bool => urlResponds(SERVE_URL), 'artisan serve');
    openBrowser(SERVE_URL);
    echo '[ok] ' . SERVE_URL . " を開きました。停止ボタンでまとめて止めます\n";
    return proc_close($proc);
}

$url = appUrl($root);
$laragon = laragonExe($root);
if ($laragon === null && !urlResponds($url)) exit(runArtisanDev($root, $url));
if ($laragon !== null) ensureLaragon($laragon, $url);

if (portOpen(VITE_PORT)) {
    echo '[ok] Vite は起動済み (:' . VITE_PORT . ")\n";
    openBrowser($url);
    exit(0);
}

$vite = startVite($root);
openBrowser($url);
echo "[ok] {$url} を開きました。停止ボタンで Vite を止めます\n";
exit(proc_close($vite));
