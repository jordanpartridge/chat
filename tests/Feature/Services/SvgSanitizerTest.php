<?php

use App\Services\SvgSanitizer;

it('removes script tags from svg content', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><script>alert("xss")</script><circle cx="50" cy="50" r="40"/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('<script>')
        ->and($result)->not->toContain('alert')
        ->and($result)->toContain('<circle');
});

it('removes self-closing script tags', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><script src="evil.js"/><circle cx="50" cy="50" r="40"/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('<script')
        ->and($result)->toContain('<circle');
});

it('removes foreignObject tags', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><foreignObject><body><script>evil()</script></body></foreignObject></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('<foreignObject')
        ->and($result)->not->toContain('<body');
});

it('removes iframe tags', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><iframe src="evil.html"></iframe><rect/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('<iframe')
        ->and($result)->toContain('<rect');
});

it('removes event handler attributes', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><circle onload="evil()" onclick="evil()" cx="50" cy="50" r="40"/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('onload')
        ->and($result)->not->toContain('onclick')
        ->and($result)->toContain('cx="50"');
});

it('removes javascript urls in href', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><a href="javascript:alert(1)"><text>Click</text></a></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('javascript:')
        ->and($result)->toContain('<text>Click</text>');
});

it('removes javascript urls in xlink:href', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><a xlink:href="javascript:evil()"><text>Link</text></a></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('javascript:');
});

it('removes data urls in href', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><a href="data:text/html,<script>evil()</script>"><text>Click</text></a></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('data:');
});

it('removes style tags', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><style>body { display: none; }</style><circle/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('<style')
        ->and($result)->toContain('<circle');
});

it('handles case insensitive tags', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><SCRIPT>evil()</SCRIPT><circle/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('SCRIPT')
        ->and($result)->not->toContain('evil');
});

it('handles case insensitive event handlers', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><circle ONLOAD="evil()" cx="50"/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('ONLOAD')
        ->and($result)->toContain('cx="50"');
});

it('preserves valid svg content', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="blue"/><rect x="10" y="10" width="20" height="20"/></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->toBe($svg);
});

it('handles multiple dangerous elements', function () {
    $sanitizer = new SvgSanitizer;

    $svg = '<svg><script>one()</script><circle onclick="two()"/><iframe></iframe><a href="javascript:three()">Link</a></svg>';
    $result = $sanitizer->sanitize($svg);

    expect($result)->not->toContain('<script')
        ->and($result)->not->toContain('onclick')
        ->and($result)->not->toContain('<iframe')
        ->and($result)->not->toContain('javascript:');
});
