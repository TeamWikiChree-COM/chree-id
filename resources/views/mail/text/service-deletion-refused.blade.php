{{ __('mail.service_deletion_refused.intro', ['service' => $serviceName]) }}

@if ($reason !== null)
{{ __('mail.service_deletion_refused.reason', ['reason' => $reason]) }}

@endif
{{ __('mail.service_deletion_refused.next') }}

{{ __('mail.service_deletion_refused.note') }}

--
{{ config('app.name') }} by Team WikiChree.COM
