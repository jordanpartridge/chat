@extends('artifacts.renderers.layout')

@push('styles')
<style>
    body {
        display: flex;
        justify-content: center;
        padding: 16px;
        background: #fff;
    }
    @media (prefers-color-scheme: dark) {
        body { background: #1a1a1a; }
    }
</style>
@endpush

@section('content')
<pre class="mermaid">{{ $content }}</pre>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/mermaid@10.6.1/dist/mermaid.min.js"></script>
<script>
    mermaid.initialize({
        startOnLoad: true,
        theme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'default'
    });
</script>
@endpush
