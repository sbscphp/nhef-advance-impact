@extends('emails.layouts.base')

@section('content')
    <p style="margin:0 0 20px 0; line-height:1.6; color:{{ $theme->text_color }};">{{ $bodyText }}</p>

    @if(filled($actionUrl ?? null))
        @include('emails.components.button', ['url' => $actionUrl, 'label' => $actionText ?? 'View details'])
    @endif
@endsection
