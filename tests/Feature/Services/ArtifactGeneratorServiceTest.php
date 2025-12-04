<?php

use App\Models\Artifact;
use App\Services\ArtifactGeneratorService;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createFakeTextResponse(string $text): TextResponse
{
    return new TextResponse(
        steps: collect([]),
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        meta: new Meta('fake-id', 'fake-model'),
        messages: collect([]),
    );
}

it('generates react artifact content', function () {
    Prism::fake([createFakeTextResponse('export default function App() { return <div>Hello</div>; }')]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_REACT,
        name: 'Test Component',
        purpose: 'Display a hello message',
    );

    expect($content)->toBe('export default function App() { return <div>Hello</div>; }');
});

it('generates vue artifact content', function () {
    Prism::fake([createFakeTextResponse('const App = { template: "<div>Hello Vue</div>" }')]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_VUE,
        name: 'Test Vue Component',
        purpose: 'Display a hello message in Vue',
    );

    expect($content)->toContain('Hello Vue');
});

it('generates html artifact content', function () {
    Prism::fake([createFakeTextResponse('<html><body><h1>Hello World</h1></body></html>')]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_HTML,
        name: 'Test HTML Page',
        purpose: 'Display a hello world page',
    );

    expect($content)->toContain('<h1>Hello World</h1>');
});

it('generates svg artifact content', function () {
    Prism::fake([createFakeTextResponse('<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>')]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_SVG,
        name: 'Test Circle',
        purpose: 'Draw a circle',
    );

    expect($content)->toContain('<svg');
    expect($content)->toContain('<circle');
});

it('generates mermaid diagram content', function () {
    Prism::fake([createFakeTextResponse("graph TD\n    A[Start] --> B[End]")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_MERMAID,
        name: 'Simple Flow',
        purpose: 'Show a simple flowchart',
    );

    expect($content)->toContain('graph TD');
});

it('generates code artifact content', function () {
    Prism::fake([createFakeTextResponse('function hello() { console.log("world"); }')]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_CODE,
        name: 'Hello Function',
        purpose: 'Log hello world to console',
    );

    expect($content)->toContain('console.log');
});

it('includes requirements in prompt when provided', function () {
    Prism::fake([createFakeTextResponse('const result = "with requirements";')]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_CODE,
        name: 'Test',
        purpose: 'Test purpose',
        requirements: 'Use specific colors and layout',
    );

    expect($content)->toBe('const result = "with requirements";');
});

it('strips markdown code fences from response', function () {
    Prism::fake([createFakeTextResponse("```javascript\nconsole.log('hello');\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_CODE,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe("console.log('hello');");
});

it('strips jsx code fences from response', function () {
    Prism::fake([createFakeTextResponse("```jsx\nexport default App;\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_REACT,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe('export default App;');
});

it('strips tsx code fences from response', function () {
    Prism::fake([createFakeTextResponse("```tsx\nexport default App;\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_REACT,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe('export default App;');
});

it('strips vue code fences from response', function () {
    Prism::fake([createFakeTextResponse("```vue\n<template>Hello</template>\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_VUE,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe('<template>Hello</template>');
});

it('strips html code fences from response', function () {
    Prism::fake([createFakeTextResponse("```html\n<div>Hello</div>\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_HTML,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe('<div>Hello</div>');
});

it('strips mermaid code fences from response', function () {
    Prism::fake([createFakeTextResponse("```mermaid\ngraph LR\n    A --> B\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_MERMAID,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe("graph LR\n    A --> B");
});

it('strips generic code fences from response', function () {
    Prism::fake([createFakeTextResponse("```\nsome code\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_CODE,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe('some code');
});

it('cleans mermaid fence remnants specifically', function () {
    Prism::fake([createFakeTextResponse("```mermaid\ngraph TD\n  A-->B\n```")]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_MERMAID,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->not->toContain('```')
        ->and($content)->toContain('graph TD');
});

it('strips react import statements', function () {
    $code = <<<'CODE'
import React from 'react';
import { useState } from 'react';

function App() {
  const [count, setCount] = useState(0);
  return <div>{count}</div>;
}
CODE;

    Prism::fake([createFakeTextResponse($code)]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_REACT,
        name: 'Counter',
        purpose: 'A counter component',
    );

    expect($content)->not->toContain('import React')
        ->and($content)->not->toContain('import { useState }')
        ->and($content)->toContain('function App()');
});

it('strips vue import statements', function () {
    $code = <<<'CODE'
import { ref, computed } from 'vue';

const App = {
  setup() {
    const count = ref(0);
    return { count };
  }
};
CODE;

    Prism::fake([createFakeTextResponse($code)]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_VUE,
        name: 'Counter',
        purpose: 'A counter component',
    );

    expect($content)->not->toContain('import { ref')
        ->and($content)->toContain('const App');
});

it('handles code fences not at start of response', function () {
    $response = "Here's the code:\n\n```jsx\nfunction App() { return <div>Hello</div>; }\n```\n\nThis should work!";

    Prism::fake([createFakeTextResponse($response)]);

    $service = new ArtifactGeneratorService;
    $content = $service->generate(
        type: Artifact::TYPE_REACT,
        name: 'Test',
        purpose: 'Test purpose',
    );

    expect($content)->toBe('function App() { return <div>Hello</div>; }');
});
