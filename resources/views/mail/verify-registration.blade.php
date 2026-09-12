@extends('mail.layout')

@section('body')
    <p>{{ __('mail.verify_registration.intro') }}</p>

    <p>{{ __('mail.verify_registration.cta_html') }}</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => __('mail.verify_registration.button_label')])

    <p>{{ __('mail.verify_registration.after_click') }}<br>
    {{ __('mail.verify_registration.not_created_yet') }}</p>

    <p>{{ __('mail.verify_registration.ttl', ['minutes' => $ttlMinutes]) }}</p>
@endsection
