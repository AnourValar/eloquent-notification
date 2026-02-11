@component('mail::message')
  # {{ trans('notification::confirm.notification.mail.body_line1') }}

  {{ trans('notification::confirm.notification.mail.body_line2') }}

  @component('mail::panel')
    {{ trans('notification::confirm.notification.mail.body_code') }}: **{{ $code }}**
  @endcomponent

  @component('mail::subcopy')
    {{ trans('notification::confirm.notification.mail.body_line3') }}
  @endcomponent
@endcomponent
