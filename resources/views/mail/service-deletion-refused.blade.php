@extends('mail.layout')

@section('body')
    <p>{{ __('mail.service_deletion_refused.intro', ['service' => $serviceName]) }}</p>

    @if ($reason !== null)
        <p>{{ __('mail.service_deletion_refused.reason', ['reason' => $reason]) }}</p>
    @endif

    <p>{{ __('mail.service_deletion_refused.next') }}</p>
@endsection

@section('note')
    {{ __('mail.service_deletion_refused.note') }}
@endsection
