<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" href="/icon.png">
    @viteReactRefresh
    @vite('resources/js/app.tsx')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
