@extends('emails.layouts.base')

@php
    $subject = 'Payment received - '.$campaignTitle;
    $headline = "Thank you, {$recipientName}!";
    $lead = 'We\'ve received your payment towards "'.$campaignTitle.'". Here\'s your receipt for your records.';
    $supportEmail = $theme->support_email ?? 'support@icoba.com';
@endphp

@section('content')
    @include('emails.components.details-table', [
        'rows' => [
            ['label' => 'Campaign', 'value' => $campaignTitle],
            ['label' => 'Amount', 'value' => $amountFormatted],
            ['label' => 'Reference', 'value' => $reference],
        ],
    ])

    <p style="margin:0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">If you did not make this payment, please contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
@endsection
