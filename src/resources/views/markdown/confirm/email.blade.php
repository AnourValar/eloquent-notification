@component('mail::message')
  # {{ trans('eloquent_notification::confirm.notification.mail.body_line1') }}

  {{ trans('eloquent_notification::confirm.notification.mail.body_line2') }}

  @component('mail::panel')
    {{ trans('eloquent_notification::confirm.notification.mail.body_code') }}: **{{ $code }}**
  @endcomponent

  @component('mail::subcopy')
    {{ trans('eloquent_notification::confirm.notification.mail.body_line3') }}
  @endcomponent
@endcomponent
