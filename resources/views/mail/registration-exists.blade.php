@extends('mail.layout')

@section('body')
    <p>{{ __('mail.registration_exists.intro') }}</p>

    <p>{{ __('mail.registration_exists.cta_html') }}</p>

    @include('mail.button', ['url' => $loginUrl, 'label' => __('mail.registration_exists.button_label')])

    <p>{{ __('mail.registration_exists.forgot_password') }}</p>
@endsection

@section('note')
    {{ __('mail.common.default_note') }}<br>
    {{ __('mail.registration_exists.note_extra') }}
@endsection
