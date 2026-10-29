@extends('emails.layouts.base')

@php
    $subject = 'Welcome to '.config('app.name', 'NHEF Advanced Impact').', '.$institutionName;
    $headline = 'Welcome, '.$institutionName.'!';
    $lead = 'Your institution has been added to the '.config('app.name', 'NHEF Advanced Impact').' platform by an administrator.';
    $supportEmail = $theme->support_email ?? 'support@nhefadvancedimpact.com';
@endphp

@section('content')
    @include('emails.components.details-table', [
        'rows' => [
            ['label' => 'Institution', 'value' => $institutionName],
        ],
    ])

    @if($inviteMessage !== null && $inviteMessage !== '')
        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">{{ $inviteMessage }}</p>
    @endif

    @if(! empty($setPasswordUrl))
        @include('emails.components.button', ['url' => $setPasswordUrl, 'label' => 'Create Your Password'])

        <p style="margin:0 0 16px; font-size:12px; line-height:1.6; color:{{ $theme->muted_text_color }};">
            If the button does not work, copy this link into your browser:<br>
            <span style="word-break:break-all; color:{{ $theme->text_color }};">{{ $setPasswordUrl }}</span>
        </p>
    @endif

    <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">Questions? Contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
@endsection
