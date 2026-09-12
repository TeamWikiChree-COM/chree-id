@extends('mail.layout')

@section('body')
    {{-- 強調のために {!! !!} を使う。差し込む値は e() で escape 済みで、
         文面そのものは lang/server/*.json（こちらが書いたもの）しか来ない --}}
    <p>{!! __('mail.email_change_notice.intro', ['email' => '<strong>' . e($newEmail) . '</strong>']) !!}</p>

    <p>{{ __('mail.email_change_notice.confirm_note') }}</p>
@endsection

@section('note')
    <strong>{{ __('mail.email_change_notice.note_line1') }}</strong><br>
    {{ __('mail.email_change_notice.note_line2') }}
@endsection
