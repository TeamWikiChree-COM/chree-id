@extends('mail.layout')

@section('body')
    <p>ChreeID のメールアドレスを、このアドレスに変更する申し込みを受け付けました。</p>

    <p>変更を確定するには、以下のボタンをクリックしてください：</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => 'メールアドレスを変更する'])

    <p>このリンクを開くまで、アドレスは変更されません。</p>

    <p>このリンクは{{ $ttlMinutes }}分間有効です。</p>
@endsection
