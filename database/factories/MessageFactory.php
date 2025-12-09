<?php

namespace Database\Factories;

use App\Models\Chat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_id' => Chat::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'parts' => ['text' => fake()->paragraph()],
        ];
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'user',
        ]);
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
        ]);
    }

    public function withMarkdownContent(): static
    {
        $markdownContent = <<<'MARKDOWN'
# Heading 1

Here is some **bold text** and *italic text*.

## Code Example

Here's an inline `code` snippet.

```php
<?php
function hello(): string
{
    return 'Hello, World!';
}
```

## Lists

- First item
- Second item
- Third item

1. Numbered one
2. Numbered two
3. Numbered three

## Blockquote

> This is a blockquote with some wisdom.

## Table

| Name | Value |
|------|-------|
| Foo  | Bar   |
| Baz  | Qux   |

## Link

Check out [Laravel](https://laravel.com) for more info.
MARKDOWN;

        return $this->state(fn (array $attributes) => [
            'parts' => ['text' => $markdownContent],
        ]);
    }
}
