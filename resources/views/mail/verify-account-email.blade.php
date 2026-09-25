@extends('mail.layout')

@section('body')
    <p>{{ __('mail.verify_account_email.intro') }}</p>

    <p>{{ __('mail.verify_account_email.cta_html') }}</p>

    @include('mail.button', ['url' => $verifyUrl, 'label' => __('mail.verify_account_email.button_label')])

    <p>{{ __('mail.verify_account_email.usage') }}</p>

    <p>{{ __('mail.verify_account_email.ttl', ['minutes' => $ttlMinutes]) }}</p>
@endsection
