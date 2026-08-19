@extends('emails.layouts.base')

@php
    $subject = 'Reminder - '.$eventTitle;
    $headline = "See you there, {$recipientName}!";
    $lead = 'This is a reminder that "'.$eventTitle.'" is coming up. Here are the details.';
    $supportEmail = $theme->support_email ?? 'support@icoba.com';
@endphp

@section('content')
    @include('emails.components.details-table', [
        'rows' => [
            ['label' => 'Event', 'value' => $eventTitle],
            ['label' => 'When', 'value' => $eventDateFormatted],
            ['label' => 'Where', 'value' => $eventLocation],
        ],
    ])

    <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">Questions? Contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
@endsection
