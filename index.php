<?php
/**
 * ChreeID - Root Front Controller with Debug Catch
 */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo '<div style="background:#fee;color:#900;padding:20px;border:2px solid #900;font-family:sans-serif;">';
        echo '<h2>【PHP Fatal Error】</h2>';
        echo '<p><strong>メッセージ:</strong> ' . htmlspecialchars($error['message']) . '</p>';
        echo '<p><strong>ファイル:</strong> ' . htmlspecialchars($error['file']) . ' (行: ' . $error['line'] . ')</p>';
        echo '</div>';
    }
});

try {
    require_once __DIR__ . '/public/index.php';
} catch (\Throwable $e) {
    echo '<div style="background:#fee;color:#900;padding:20px;border:2px solid #900;font-family:sans-serif;">';
    echo '<h2>【Laravel Startup Exception】</h2>';
    echo '<p><strong>メッセージ:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>ファイル:</strong> ' . htmlspecialchars($e->getFile()) . ' (行: ' . $e->getLine() . ')</p>';
    echo '<pre style="background:#fff;padding:10px;border:1px solid #ccc;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}
