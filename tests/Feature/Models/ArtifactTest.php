<?php

use App\Models\Artifact;
use App\Models\Message;

describe('factory', function () {
    it('creates a valid artifact', function () {
        $artifact = Artifact::factory()->create();
        expect($artifact)->toBeInstanceOf(Artifact::class);
    });

    it('uses UUIDs as primary key', function () {
        $artifact = Artifact::factory()->create();
        expect($artifact->id)->toMatch('/^[a-f0-9-]{36}$/');
    });

    it('has fillable attributes', function () {
        $message = Message::factory()->create();
        $artifact = Artifact::create([
            'message_id' => $message->id,
            'identifier' => 'test-identifier',
            'type' => Artifact::TYPE_CODE,
            'title' => 'Test Title',
            'language' => 'php',
            'content' => '<?php echo "hello";',
            'version' => 1,
        ]);

        expect($artifact->identifier)->toBe('test-identifier')
            ->and($artifact->type)->toBe(Artifact::TYPE_CODE)
            ->and($artifact->title)->toBe('Test Title')
            ->and($artifact->language)->toBe('php')
            ->and($artifact->version)->toBe(1);
    });
});

describe('relationships', function () {
    it('belongs to a message', function () {
        $artifact = Artifact::factory()->create();
        expect($artifact->message)->toBeInstanceOf(Message::class);
    });
});

describe('constants', function () {
    it('defines all artifact type constants', function () {
        expect(Artifact::TYPE_CODE)->toBe('code')
            ->and(Artifact::TYPE_MARKDOWN)->toBe('markdown')
            ->and(Artifact::TYPE_HTML)->toBe('html')
            ->and(Artifact::TYPE_SVG)->toBe('svg')
            ->and(Artifact::TYPE_MERMAID)->toBe('mermaid')
            ->and(Artifact::TYPE_REACT)->toBe('react')
            ->and(Artifact::TYPE_VUE)->toBe('vue');
    });

    it('has TYPES array constant with all types', function () {
        expect(Artifact::TYPES)->toContain(
            Artifact::TYPE_CODE,
            Artifact::TYPE_MARKDOWN,
            Artifact::TYPE_HTML,
            Artifact::TYPE_SVG,
            Artifact::TYPE_MERMAID,
            Artifact::TYPE_REACT,
            Artifact::TYPE_VUE
        )->toHaveCount(7);
    });
});

describe('type identification', function () {
    it('correctly identifies code artifact', function () {
        $artifact = Artifact::factory()->code()->create();
        expect($artifact->isCode())->toBeTrue()
            ->and($artifact->isMarkdown())->toBeFalse()
            ->and($artifact->isHtml())->toBeFalse()
            ->and($artifact->isSvg())->toBeFalse()
            ->and($artifact->isMermaid())->toBeFalse()
            ->and($artifact->isReact())->toBeFalse();
    });

    it('correctly identifies markdown artifact', function () {
        $artifact = Artifact::factory()->markdown()->create();
        expect($artifact->isMarkdown())->toBeTrue()
            ->and($artifact->isCode())->toBeFalse();
    });

    it('correctly identifies html artifact', function () {
        $artifact = Artifact::factory()->html()->create();
        expect($artifact->isHtml())->toBeTrue()
            ->and($artifact->isCode())->toBeFalse();
    });

    it('correctly identifies svg artifact', function () {
        $artifact = Artifact::factory()->svg()->create();
        expect($artifact->isSvg())->toBeTrue()
            ->and($artifact->isCode())->toBeFalse();
    });

    it('correctly identifies mermaid artifact', function () {
        $artifact = Artifact::factory()->mermaid()->create();
        expect($artifact->isMermaid())->toBeTrue()
            ->and($artifact->isCode())->toBeFalse();
    });

    it('correctly identifies react artifact', function () {
        $artifact = Artifact::factory()->react()->create();
        expect($artifact->isReact())->toBeTrue()
            ->and($artifact->isCode())->toBeFalse();
    });

    it('correctly identifies vue artifact', function () {
        $artifact = Artifact::factory()->vue()->create();
        expect($artifact->isVue())->toBeTrue()
            ->and($artifact->isCode())->toBeFalse();
    });
});

describe('factory states', function () {
    it('creates code artifact with factory state', function () {
        $artifact = Artifact::factory()->code('python')->create();
        expect($artifact->type)->toBe(Artifact::TYPE_CODE)
            ->and($artifact->language)->toBe('python');
    });

    it('creates markdown artifact with factory state', function () {
        $artifact = Artifact::factory()->markdown()->create();
        expect($artifact->type)->toBe(Artifact::TYPE_MARKDOWN)
            ->and($artifact->language)->toBeNull();
    });

    it('creates html artifact with factory state', function () {
        $artifact = Artifact::factory()->html()->create();
        expect($artifact->type)->toBe(Artifact::TYPE_HTML)
            ->and($artifact->language)->toBeNull();
    });

    it('creates svg artifact with factory state', function () {
        $artifact = Artifact::factory()->svg()->create();
        expect($artifact->type)->toBe(Artifact::TYPE_SVG)
            ->and($artifact->content)->toContain('<svg');
    });

    it('creates mermaid artifact with factory state', function () {
        $artifact = Artifact::factory()->mermaid()->create();
        expect($artifact->type)->toBe(Artifact::TYPE_MERMAID)
            ->and($artifact->content)->toContain('graph');
    });

    it('creates react artifact with factory state', function () {
        $artifact = Artifact::factory()->react()->create();
        expect($artifact->type)->toBe(Artifact::TYPE_REACT)
            ->and($artifact->language)->toBe('jsx');
    });

    it('creates vue artifact with factory state', function () {
        $artifact = Artifact::factory()->vue()->create();
        expect($artifact->type)->toBe(Artifact::TYPE_VUE)
            ->and($artifact->language)->toBe('vue');
    });
});
