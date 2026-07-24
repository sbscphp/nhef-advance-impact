@extends('emails.layouts.base')

@php
    $subject = 'Verify your email address';
    $recipientName = $user->displayName() ?: 'there';
    $headline = "Verify Your Email Address";
    $brandName = $theme->brand_name ?? config('app.name');
    $lead = "Welcome back to our global community, {$recipientName}! Thank you for registering your alumni account on the ".$brandName.' platform. To ensure the security of your account and protect our alumni network data, please verify your email address by clicking the secure link below. This link expires in '.$expiresInMinutes.' minutes.';
@endphp

@section('content')
    @include('emails.components.button', ['url' => $verifyUrl, 'label' => 'Verify Email Address'])

    <p style="margin:0 0 8px 0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};"><strong>What can you do once verified?</strong></p>
    <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">Once your account is activated, you can log into your self-service portal to:</p>
    <ul style="margin:0 0 16px 0; padding-left:20px; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">
        <li style="margin-bottom:8px;"><strong>Update Your Profile:</strong> Keep your contact details, occupation, and communication preferences current.</li>
        <li style="margin-bottom:8px;"><strong>Manage Your Giving:</strong> Securely contribute to upcoming campaigns in your preferred currency (NGN, USD, GBP, EUR) and download official receipts.</li>
        <li><strong>See Your Impact:</strong> Access personalized impact reports showing exactly how your alumni contributions are driving real-world project outcomes.</li>
    </ul>

    <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">If you did not initiate this registration request, please ignore this email or contact our system administration team immediately.</p>

    <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:{{ $theme->text_color }};">Welcome to the network,<br>The Advancement Team</p>

    <p style="margin:0; font-size:13px; line-height:1.6; color:{{ $theme->muted_text_color }};">If the button does not work, copy and paste this link into your browser:<br>
        <span style="word-break:break-all; color:{{ $theme->text_color }};">{{ $verifyUrl }}</span>
    </p>
@endsection
