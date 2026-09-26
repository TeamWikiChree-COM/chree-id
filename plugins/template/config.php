<?php

// 置いておくと、本体が config('template.…') で読めるようにする (フォルダ名がキーになる)。
// 本体の config/template.php に同じキーを書くと、そちらが優先される。
return [
    'greeting' => env('TEMPLATE_GREETING', 'Hello'),
];