<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Artifact;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class ArtifactGeneratorService
{
    /**
     * Generate artifact content using a coding model.
     */
    public function generate(
        string $type,
        string $name,
        string $purpose,
        ?string $requirements = null,
    ): string {
        $prompt = $this->buildPrompt($type, $name, $purpose, $requirements);

        $response = Prism::text()
            ->using(Provider::Ollama, $this->getModelForType($type))
            ->withSystemPrompt($this->getSystemPrompt($type))
            ->withPrompt($prompt)
            ->asText();

        return $this->extractCode($response->text, $type);
    }

    /**
     * Get the best model for the artifact type.
     * Uses codellama for code-heavy tasks, llama3.2 for diagrams.
     */
    private function getModelForType(string $type): string
    {
        return match ($type) {
            Artifact::TYPE_REACT,
            Artifact::TYPE_VUE,
            Artifact::TYPE_HTML,
            Artifact::TYPE_SVG => 'codellama',
            Artifact::TYPE_MERMAID => 'llama3.2',
            default => 'codellama',
        };
    }

    /**
     * Get the system prompt for code generation.
     */
    private function getSystemPrompt(string $type): string
    {
        $basePrompt = 'You are an expert code generator. Generate clean, working code. Output ONLY the code, no explanations.';

        return match ($type) {
            Artifact::TYPE_REACT => <<<'PROMPT'
You are an expert React developer. Generate a complete, self-contained React component.
- Use functional components with hooks (useState, useEffect, etc.)
- DO NOT import external libraries - React, useState, useEffect are available globally
- Use inline styles or Tailwind CSS classes for styling
- Make it interactive and visually appealing
- The component must be COMPLETELY SELF-CONTAINED with NO external dependencies
- The component should be named "App" and be the default export
- Output ONLY the JSX code, no markdown, no explanations
PROMPT,
            Artifact::TYPE_VUE => <<<'PROMPT'
You are an expert Vue.js developer. Generate a complete Vue 3 component.
- Use Composition API with setup()
- Use inline styles or Tailwind CSS classes for styling
- Make it interactive and visually appealing
- The component must be COMPLETELY SELF-CONTAINED with NO external dependencies
- The component should be named "App"
- Output ONLY the JavaScript code (not SFC format), no markdown, no explanations
- Vue's createApp, ref, computed, reactive, onMounted are available globally
PROMPT,
            Artifact::TYPE_HTML => <<<'PROMPT'
You are an expert web developer. Generate a complete HTML document.
- Include all CSS inline in a <style> tag
- Include all JavaScript inline in a <script> tag
- DO NOT use external CDN links or libraries
- The page must be COMPLETELY SELF-CONTAINED with NO external dependencies
- Make it interactive and visually appealing
- Output ONLY the HTML code, no markdown, no explanations
PROMPT,
            Artifact::TYPE_SVG => <<<'PROMPT'
You are an expert SVG artist. Generate a complete SVG graphic.
- Use proper viewBox for scaling
- Make it visually appealing with appropriate colors
- Output ONLY the SVG code, no markdown, no explanations
PROMPT,
            Artifact::TYPE_MERMAID => <<<'PROMPT'
You are an expert at creating Mermaid diagrams. Generate a Mermaid diagram.
- Use appropriate diagram type (flowchart, sequence, class, etc.)
- Keep it clear and readable
- Output ONLY the Mermaid code, no markdown fence, no explanations
PROMPT,
            default => $basePrompt,
        };
    }

    /**
     * Build the generation prompt.
     */
    private function buildPrompt(
        string $type,
        string $name,
        string $purpose,
        ?string $requirements,
    ): string {
        $prompt = "Create: {$name}\n\nPurpose: {$purpose}";

        if ($requirements !== null && $requirements !== '') {
            $prompt .= "\n\nRequirements:\n{$requirements}";
        }

        return $prompt;
    }

    /**
     * Extract clean code from the response, removing markdown fences if present.
     */
    private function extractCode(string $response, string $type): string
    {
        $code = trim($response);

        // Extract code from markdown fences (anywhere in the response)
        $patterns = [
            '/```(?:jsx?|tsx?|react|javascript|typescript)\s*\n(.*?)```/s',
            '/```(?:vue|html|svg|mermaid)\s*\n(.*?)```/s',
            '/```\s*\n(.*?)```/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                $code = trim($matches[1]);

                break;
            }
        }

        // Remove any remaining fence markers
        $code = preg_replace('/^```\w*\s*/', '', $code);
        $code = preg_replace('/\s*```$/', '', $code);

        // For React/Vue: remove import statements (we load React/Vue via CDN)
        if ($type === Artifact::TYPE_REACT || $type === Artifact::TYPE_VUE) {
            $code = preg_replace('/^\s*import\s+.*?[\'"].*?[\'"];?\s*$/m', '', $code);
            $code = preg_replace('/^\s*import\s+\{[^}]*\}\s+from\s+[\'"].*?[\'"];?\s*$/m', '', $code);
        }

        // Clean up multiple blank lines
        $code = preg_replace('/\n{3,}/', "\n\n", $code);

        return trim($code);
    }
}
