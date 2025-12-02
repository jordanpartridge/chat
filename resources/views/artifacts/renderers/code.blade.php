@extends('artifacts.renderers.layout')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.30.0/themes/prism-tomorrow.min.css" crossorigin="anonymous">
<style>
    body { background: #2d2d2d; }
    pre { margin: 0; padding: 16px; overflow-x: auto; }
    code { font-family: 'Fira Code', 'JetBrains Mono', monospace; font-size: 14px; line-height: 1.5; }
</style>
@endpush

@section('content')
<pre><code class="language-{{ $language }}">{{ $content }}</code></pre>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.30.0/prism.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.30.0/plugins/autoloader/prism-autoloader.min.js" crossorigin="anonymous"></script>
@endpush
