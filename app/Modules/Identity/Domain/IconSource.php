<?php
namespace App\Modules\Identity\Domain;

// アイコンをどこから出すか

// 画像そのものは保存経路が違うだけで、表示上はどれも1枚の絵になる。
// 「持っていない」を UPLOAD で path が null の状態と区別するために独立させている。
enum IconSource: string {
    case NONE = 'none'; // 未設定。頭文字などで代替する
    case GRAVATAR = 'gravatar'; // メールアドレスから Gravatar を引く
    case UPLOAD = 'upload'; // 本人がアップロードした画像
}
