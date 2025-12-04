@extends('artifacts.renderers.layout')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css@5.2.0/github-markdown.min.css">
<style>
    body { padding: 16px; background: #fff; }
    .markdown-body { max-width: 800px; margin: 0 auto; }
    @media (prefers-color-scheme: dark) {
        body { background: #0d1117; }
        .markdown-body { color-scheme: dark; }
    }
</style>
@endpush

@section('content')
<div class="markdown-body" id="content"></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
<script>
    document.getElementById('content').innerHTML = marked.parse(@json($content));
</script>
@endpush
