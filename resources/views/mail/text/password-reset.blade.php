{{ __('mail.password_reset.intro') }}

{{ __('mail.password_reset.cta_text') }}

{{ $resetUrl }}

{{ __('mail.password_reset.ttl', ['minutes' => $ttlMinutes]) }}

{{ __('mail.common.default_note') }}
{{ __('mail.password_reset.note_extra') }}

--
{{ config('app.name') }} by Team WikiChree.COM
