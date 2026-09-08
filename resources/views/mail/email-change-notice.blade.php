@extends('mail.layout')

@section('body')
    <p>ChreeID のメールアドレスを <strong>{{ $newEmail }}</strong> に変更する申し込みがありました。</p>

    <p>変更は、新しいアドレス宛のリンクが開かれた時点で確定します。
    この時点ではまだ変更されていません。</p>
@endsection

@section('note')
    <strong>心当たりがない場合は、パスワードを変更してください。</strong><br>
    第三者がアカウントに入っている可能性があります。
@endsection
