@extends('artifacts.renderers.layout')

@push('styles')
<script src="https://cdn.tailwindcss.com" crossorigin="anonymous"></script>
@endpush

@section('content')
<div id="app"></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js" crossorigin="anonymous"></script>
<script>
const { createApp, ref, computed, reactive, onMounted, watch, nextTick } = Vue;

{!! $content !!}

// Try to find and mount the component
if (typeof App !== 'undefined') {
    createApp(App).mount('#app');
} else if (typeof Component !== 'undefined') {
    createApp(Component).mount('#app');
}
</script>
@endpush
