@extends('emails.layouts.base')

@php
    $subject = 'AdvanceImpact Exchange Rate Alert';
    $headline = 'Exchange Rate Alert';
    $lead = $alertMessage;
@endphp
