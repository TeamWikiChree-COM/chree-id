<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('apidocs.page.title', ['version' => $version]) }}</title>
    <meta name="description" content="{{ __('apidocs.page.description') }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" href="/icon.png">
</head>
<body style="margin: 0">
    <noscript>{{ __('apidocs.page.noscript') }}</noscript>
    {{-- 仕様は OpenApiSpec が組み立てる。この画面に説明を書き足さないこと --}}
    <script id="api-reference" data-url="{{ $specUrl }}"></script>
    {{-- 版を固定する。無指定だと上流の破壊的変更がデプロイ無しで本番に出る --}}
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.68.0"></script>
</body>
</html>
