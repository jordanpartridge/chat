<?php

use App\Models\Artifact;
use App\Models\Message;
use App\Services\ArtifactGeneratorService;
use App\Tools\CreateArtifactTool;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createToolTextResponse(string $text): TextResponse
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

it('returns error when message context is not set', function () {
    $generator = $this->mock(ArtifactGeneratorService::class);
    $tool = new CreateArtifactTool($generator);

    $result = $tool->execute(
        name: 'Test Artifact',
        purpose: 'This is a detailed test purpose for the artifact',
        type: 'html'
    );

    expect($result)->toBe('Error: Message context not set. Cannot create artifact.');
});

it('returns error when purpose is too short', function () {
    $generator = $this->mock(ArtifactGeneratorService::class);
    $tool = new CreateArtifactTool($generator);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Test',
        purpose: 'Too short',
        type: 'html'
    );

    expect($result)->toBe('Error: Purpose is too vague. Please provide more detail about what the artifact should do.');
});

it('creates react artifact successfully', function () {
    Prism::fake([createToolTextResponse('export default function App() { return <div>Hello</div>; }')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Test Component',
        purpose: 'Display a greeting message in a React component',
        type: 'react'
    );

    expect($result)->toContain('Artifact created successfully')
        ->and($result)->toContain('Test Component');

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact)->not->toBeNull()
        ->and($artifact->type)->toBe(Artifact::TYPE_REACT)
        ->and($artifact->language)->toBe('jsx');
});

it('creates vue artifact successfully', function () {
    Prism::fake([createToolTextResponse('const App = { template: "<div>Hello</div>" }')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Vue Component',
        purpose: 'Display a greeting message in a Vue component',
        type: 'vue'
    );

    expect($result)->toContain('Artifact created successfully');

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->type)->toBe(Artifact::TYPE_VUE)
        ->and($artifact->language)->toBe('vue');
});

it('creates html artifact successfully', function () {
    Prism::fake([createToolTextResponse('<html><body>Hello</body></html>')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'HTML Page',
        purpose: 'Display a simple hello world HTML page',
        type: 'html'
    );

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->type)->toBe(Artifact::TYPE_HTML)
        ->and($artifact->language)->toBe('html');
});

it('creates svg artifact successfully', function () {
    Prism::fake([createToolTextResponse('<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Circle SVG',
        purpose: 'Draw a simple circle using SVG graphics',
        type: 'svg'
    );

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->type)->toBe(Artifact::TYPE_SVG)
        ->and($artifact->language)->toBeNull();
});

it('creates mermaid artifact successfully', function () {
    Prism::fake([createToolTextResponse('graph TD\n    A --> B')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Flow Diagram',
        purpose: 'Display a simple flowchart diagram',
        type: 'mermaid'
    );

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->type)->toBe(Artifact::TYPE_MERMAID)
        ->and($artifact->language)->toBeNull();
});

it('creates markdown artifact successfully', function () {
    Prism::fake([createToolTextResponse('# Hello World\n\nThis is markdown content.')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Documentation',
        purpose: 'Display formatted markdown documentation',
        type: 'markdown'
    );

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->type)->toBe(Artifact::TYPE_MARKDOWN)
        ->and($artifact->language)->toBe('markdown');
});

it('defaults to html for unknown type', function () {
    Prism::fake([createToolTextResponse('<div>Default content</div>')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'Unknown Type',
        purpose: 'Testing default type behavior for artifacts',
        type: 'unknown'
    );

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->type)->toBe(Artifact::TYPE_HTML);
});

it('passes requirements to generator when provided', function () {
    Prism::fake([createToolTextResponse('Generated content')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute(
        name: 'With Requirements',
        purpose: 'Testing requirements parameter passing',
        type: 'html',
        requirements: 'Use blue theme and centered layout'
    );

    expect($result)->toContain('Artifact created successfully');
});

it('sets message id via fluent method', function () {
    $generator = $this->mock(ArtifactGeneratorService::class);
    $tool = new CreateArtifactTool($generator);
    $message = Message::factory()->create();

    $returnValue = $tool->setMessageId($message->id);

    expect($returnValue)->toBe($tool);
});

it('creates artifact with unique identifier', function () {
    Prism::fake([createToolTextResponse('Content 1'), createToolTextResponse('Content 2')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $tool->execute('First', 'First artifact purpose description', 'html');
    $tool->execute('Second', 'Second artifact purpose description', 'html');

    $artifacts = Artifact::where('message_id', $message->id)->get();
    expect($artifacts)->toHaveCount(2);
    expect($artifacts[0]->identifier)->not->toBe($artifacts[1]->identifier);
});

it('sets artifact version to 1', function () {
    Prism::fake([createToolTextResponse('Content')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $tool->execute('Test', 'Test purpose for version check', 'html');

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($artifact->version)->toBe(1);
});

it('includes artifact id in success message', function () {
    Prism::fake([createToolTextResponse('Content')]);

    $tool = app(CreateArtifactTool::class);
    $message = Message::factory()->create();
    $tool->setMessageId($message->id);

    $result = $tool->execute('Test', 'Test purpose for ID check', 'html');

    $artifact = Artifact::where('message_id', $message->id)->first();
    expect($result)->toContain("[artifact:{$artifact->id}]");
});
