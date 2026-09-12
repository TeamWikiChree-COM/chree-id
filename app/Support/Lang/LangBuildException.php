<?php
namespace App\Support\Lang;

/**
 * 翻訳のビルドを中止させる。
 *
 * **実行するまで気づけない種類のバグをここで落とす**のが目的なので、
 * 握り潰さずに呼び出し元 (artisan / CI) まで通す。
 */
final class LangBuildException extends \RuntimeException {
}
