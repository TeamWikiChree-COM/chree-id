@extends('mail.layout')

@section('body')
    <p>このメールアドレスでは、すでに ChreeID のアカウントが作成されています。</p>

    <p>ログインは以下のボタンから行えます：</p>

    @include('mail.button', ['url' => $loginUrl, 'label' => 'ログインする'])

    <p>パスワードが分からない場合は、ログイン画面から再設定できます。</p>
@endsection

@section('note')
    心当たりがない場合は、このメールを無視してください。<br>
    アカウントの情報は変更されていません。
@endsection
