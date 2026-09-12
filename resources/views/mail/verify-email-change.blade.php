@extends('mail.layout')

@section('body')
    <p>{{ __('mail.verify_email_change.intro') }}</p>

    <p>{{ __('mail.verify_email_change.cta_html') }}</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => __('mail.verify_email_change.button_label')])

    <p>{{ __('mail.verify_email_change.not_changed_yet') }}</p>

    <p>{{ __('mail.verify_email_change.ttl', ['minutes' => $ttlMinutes]) }}</p>
@endsection
