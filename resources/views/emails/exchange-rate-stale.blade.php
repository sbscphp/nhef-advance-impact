@extends('emails.layouts.base')

@php
    $subject = 'NHEF Advance Impact Exchange Rate Alert';
    $headline = 'Exchange Rate Alert';
    $lead = $alertMessage;
@endphp
