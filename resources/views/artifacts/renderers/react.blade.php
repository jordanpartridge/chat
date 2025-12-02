@extends('artifacts.renderers.layout')

@push('styles')
<script src="https://cdn.tailwindcss.com" crossorigin="anonymous"></script>
@endpush

@section('content')
<div id="root"></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js" crossorigin="anonymous"></script>
<script>
// Expose React hooks globally so generated code can use them directly
const { useState, useEffect, useRef, useMemo, useCallback, useReducer, useContext, createContext } = React;
</script>
<script type="text/babel">
{!! $content !!}

// Try to find and render the component
const root = ReactDOM.createRoot(document.getElementById('root'));
if (typeof App !== 'undefined') {
    root.render(<App />);
} else if (typeof Component !== 'undefined') {
    root.render(<Component />);
} else if (typeof exports !== 'undefined' && exports.default) {
    const DefaultComponent = exports.default;
    root.render(<DefaultComponent />);
}
</script>
@endpush
