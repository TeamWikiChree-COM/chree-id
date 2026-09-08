@extends('mail.layout')

@section('body')
    <p>ChreeID のパスワード再設定のご依頼を受け付けました。</p>

    <p>新しいパスワードを設定するには、以下のボタンをクリックしてください：</p>

    @include('mail.button', ['url' => $resetUrl, 'label' => 'パスワードを再設定する'])

    <p>このリンクは{{ $ttlMinutes }}分間有効で、一度だけ使用できます。</p>
@endsection

@section('note')
    心当たりがない場合は、このメールを無視してください。<br>
    パスワードは変更されていません。
@endsection
