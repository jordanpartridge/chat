@extends('artifacts.renderers.layout')

@push('styles')
<style>
    body {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        background: #f5f5f5;
    }
    svg { max-width: 100%; max-height: 100vh; }
    @media (prefers-color-scheme: dark) {
        body { background: #1a1a1a; }
    }
</style>
@endpush

@section('content')
{!! $content !!}
@endsection
