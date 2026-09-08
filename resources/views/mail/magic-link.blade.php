@extends('mail.layout')

@section('body')
    <p>ChreeID へのログインリンクをお届けします。</p>

    <p>以下のボタンをクリックすると、パスワードなしでログインできます：</p>

    @include('mail.button', ['url' => $loginUrl, 'label' => 'ログインする'])

    <p>このリンクは{{ $ttlMinutes }}分間有効で、一度だけ使用できます。<br>
    2段階認証を設定している場合は、リンクを開いたあとにコードの入力が必要です。</p>
@endsection
