<?php

declare(strict_types=1);

namespace App\Tools;

use App\Models\Artifact;
use App\Services\ArtifactGeneratorService;
use Prism\Prism\Tool;

class CreateArtifactTool extends Tool
{
    public function __construct(
        private ArtifactGeneratorService $generator,
        private ?string $messageId = null,
    ) {
        $this
            ->as('create_artifact')
            ->for('Create an interactive component, diagram, or document. Use this when the user asks for visual content like dashboards, charts, diagrams, or interactive UI components.')
            ->withStringParameter('name', 'A short, descriptive name for the artifact (e.g., "Sales Dashboard", "User Flow Diagram")')
            ->withStringParameter('purpose', 'What the artifact should do or display. Be specific about functionality and data.')
            ->withEnumParameter('type', 'The type of artifact to create', ['react', 'vue', 'html', 'svg', 'mermaid', 'markdown'])
            ->withStringParameter('requirements', 'Detailed requirements including: layout, colors, data to display, interactivity, and any specific features', required: false)
            ->using($this->execute(...));
    }

    public function setMessageId(string $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function execute(string $name, string $purpose, string $type, ?string $requirements = null): string
    {
        if ($this->messageId === null) {
            return 'Error: Message context not set. Cannot create artifact.';
        }

        if (strlen($purpose) < 10) {
            return 'Error: Purpose is too vague. Please provide more detail about what the artifact should do.';
        }

        $artifactType = match ($type) {
            'react' => Artifact::TYPE_REACT,
            'vue' => Artifact::TYPE_VUE,
            'html' => Artifact::TYPE_HTML,
            'svg' => Artifact::TYPE_SVG,
            'mermaid' => Artifact::TYPE_MERMAID,
            'markdown' => Artifact::TYPE_MARKDOWN,
            default => Artifact::TYPE_HTML,
        };

        // Increase timeout for code generation (LLMs can be slow)
        $originalTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        try {
            $content = $this->generator->generate(
                type: $artifactType,
                name: $name,
                purpose: $purpose,
                requirements: $requirements,
            );
        } finally {
            // Restore original timeout
            set_time_limit((int) $originalTimeout);
        }

        $artifact = Artifact::create([
            'message_id' => $this->messageId,
            'identifier' => (string) str()->uuid(),
            'type' => $artifactType,
            'title' => $name,
            'language' => $this->getLanguageForType($artifactType),
            'content' => $content,
            'version' => 1,
        ]);

        return sprintf(
            'Artifact created successfully: [artifact:%s] - %s',
            $artifact->id,
            $name
        );
    }

    private function getLanguageForType(string $type): ?string
    {
        return match ($type) {
            Artifact::TYPE_REACT => 'jsx',
            Artifact::TYPE_VUE => 'vue',
            Artifact::TYPE_HTML => 'html',
            Artifact::TYPE_MARKDOWN => 'markdown',
            Artifact::TYPE_SVG => null,
            Artifact::TYPE_MERMAID => null,
            default => null,
        };
    }
}
