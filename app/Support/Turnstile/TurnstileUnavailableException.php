<?php
namespace App\Support\Turnstile;

use RuntimeException;

/**
 * Cloudflare 側に問い合わせられなかったときの例外。
 *
 * 「人間でないと判定された」場合とは区別する。こちらは利用者に落ち度がないので、
 * 呼び出し側は入力エラーではなく一時的な障害として扱うこと。
 */
class TurnstileUnavailableException extends RuntimeException {}
