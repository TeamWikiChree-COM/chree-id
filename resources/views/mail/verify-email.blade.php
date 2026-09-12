@extends('mail.layout')

@section('body')
    <p>{{ __('mail.verify_email.intro') }}</p>

    <p>{{ __('mail.verify_email.cta_html') }}</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => __('mail.verify_email.button_label')])

    <p>{{ __('mail.verify_email.after_confirm') }}</p>

    <p>{{ __('mail.verify_email.ttl', ['minutes' => $ttlMinutes]) }}</p>
@endsection
