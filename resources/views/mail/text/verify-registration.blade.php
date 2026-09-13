{{ __('mail.verify_registration.intro') }}

{{ __('mail.verify_registration.cta_text') }}

{{ $verifyUrl }}

{{ __('mail.verify_registration.after_click') }}
{{ __('mail.verify_registration.not_created_yet') }}

{{ __('mail.verify_registration.ttl', ['minutes' => $ttlMinutes]) }}

{{ __('mail.common.default_note') }}

--
{{ config('app.name') }} by Team WikiChree.COM
