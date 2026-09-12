@extends('mail.layout')

@section('body')
    <p>{{ __('mail.magic_link.intro') }}</p>

    <p>{{ __('mail.magic_link.cta_html') }}</p>

    @include('mail.button', ['url' => $loginUrl, 'label' => __('mail.magic_link.button_label')])

    <p>{{ __('mail.magic_link.ttl', ['minutes' => $ttlMinutes]) }}<br>
    {{ __('mail.magic_link.two_factor_note') }}</p>
@endsection
