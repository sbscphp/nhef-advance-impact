@extends('emails.layouts.base')

@php
    $subject = 'Registration confirmed - '.$eventTitle;
    $headline = "You're in, {$recipientName}!";
    $lead = 'Your registration for "'.$eventTitle.'" is confirmed. Here are the details for your records.';
    $supportEmail = $theme->support_email ?? 'support@icoba.com';
@endphp

@section('content')
    @include('emails.components.details-table', [
        'rows' => [
            ['label' => 'Event', 'value' => $eventTitle],
            ['label' => 'Amount', 'value' => $amountFormatted],
            ['label' => 'Reference', 'value' => $reference],
        ],
    ])

    <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">If you did not make this registration, please contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
@endsection
