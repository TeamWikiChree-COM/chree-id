@extends('mail.layout')

@section('body')
    <p>ChreeID に登録されているメールアドレスの確認をお願いします。</p>

    <p>以下のボタンをクリックすると、このアドレスが確認済みになります：</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => 'メールアドレスを確認する'])

    <p>確認済みになると、連携先のサービスへアカウントを引き継げるようになります。</p>

    <p>このリンクは{{ $ttlMinutes }}分間有効です。</p>
@endsection
