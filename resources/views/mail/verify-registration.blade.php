@extends('mail.layout')

@section('body')
    <p>ChreeID をご利用いただきありがとうございます。</p>

    <p>アカウントの作成を続けるには、以下のボタンをクリックしてください：</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => 'アカウント作成を続ける'])

    <p>リンクを開いたあと、パスワードを決めていただきます。<br>
    リンクを開くまでアカウントは作られません。</p>

    <p>このリンクは{{ $ttlMinutes }}分間有効です。</p>
@endsection
