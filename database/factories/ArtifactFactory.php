<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Artifact;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'identifier' => $this->faker->uuid(),
            'type' => Artifact::TYPE_CODE,
            'title' => $this->faker->sentence(3),
            'language' => 'javascript',
            'content' => 'console.log("Hello, World!");',
            'version' => 1,
        ];
    }

    public function code(string $language = 'javascript'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_CODE,
            'language' => $language,
        ]);
    }

    public function markdown(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_MARKDOWN,
            'language' => null,
            'content' => "# {$this->faker->sentence()}\n\n{$this->faker->paragraphs(3, true)}",
        ]);
    }

    public function html(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_HTML,
            'language' => null,
            'content' => '<div class="container"><h1>Hello World</h1></div>',
        ]);
    }

    public function svg(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_SVG,
            'language' => null,
            'content' => '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="blue"/></svg>',
        ]);
    }

    public function mermaid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_MERMAID,
            'language' => null,
            'content' => "graph TD\n    A[Start] --> B[End]",
        ]);
    }

    public function react(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_REACT,
            'language' => 'jsx',
            'content' => 'export default function App() { return <div>Hello React</div>; }',
        ]);
    }

    public function vue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Artifact::TYPE_VUE,
            'language' => 'vue',
            'content' => 'const App = { template: "<div>Hello Vue</div>" }',
        ]);
    }
}
