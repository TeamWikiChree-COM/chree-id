@extends('mail.layout')

@section('body')
    <p>{{ __('mail.password_reset.intro') }}</p>

    <p>{{ __('mail.password_reset.cta_html') }}</p>

    @include('mail.button', ['url' => $resetUrl, 'label' => __('mail.password_reset.button_label')])

    <p>{{ __('mail.password_reset.ttl', ['minutes' => $ttlMinutes]) }}</p>
@endsection

@section('note')
    {{ __('mail.common.default_note') }}<br>
    {{ __('mail.password_reset.note_extra') }}
@endsection
