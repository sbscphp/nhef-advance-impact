@extends('emails.layouts.base')

@php
    $subject = 'Welcome to NHEF Nexus, '.$institutionName;
    $headline = 'Welcome, '.$institutionName.'!';
    $lead = 'Your institution has been added to the NHEF Nexus platform by an administrator.';
    $supportEmail = $theme->support_email ?? 'support@icoba.com';
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

    <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">Questions? Contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
@endsection
