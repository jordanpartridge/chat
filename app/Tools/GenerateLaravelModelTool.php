<?php

declare(strict_types=1);

namespace App\Tools;

use Illuminate\Support\Facades\Artisan;
use Prism\Prism\Tool;

class GenerateLaravelModelTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('generate_laravel_model')
            ->for('Generate a Laravel Eloquent model with optional migration, factory, and seeder. Use this when the user asks to create a new model or database table.')
            ->withStringParameter('name', 'The model name in PascalCase (e.g., "BlogPost", "UserProfile")')
            ->withStringParameter('fields', 'Comma-separated field definitions in format "name:type" (e.g., "title:string,body:text,published_at:timestamp:nullable")')
            ->withEnumParameter('with', 'Additional files to generate', ['migration', 'factory', 'seeder', 'all', 'none'])
            ->withStringParameter('relationships', 'Comma-separated relationships (e.g., "belongsTo:User,hasMany:Comment")', required: false)
            ->using($this->execute(...));
    }

    public function execute(string $name, string $fields, string $with, ?string $relationships = null): string
    {
        if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            return 'Error: Model name must be in PascalCase (e.g., "BlogPost")';
        }

        $parsedFields = $this->parseFields($fields);
        if (is_string($parsedFields)) {
            return $parsedFields;
        }

        $parsedRelationships = [];
        if ($relationships !== null) {
            $parsedRelationships = $this->parseRelationships($relationships);
            if (is_string($parsedRelationships)) {
                return $parsedRelationships;
            }
        }

        $options = $this->buildArtisanOptions($with);

        Artisan::call('make:model', array_merge(['name' => $name], $options));

        $output = [];
        $output[] = "✓ Created model: app/Models/{$name}.php";

        if (in_array('--migration', array_keys($options)) || ($options['--migration'] ?? false)) {
            $output[] = "✓ Created migration for {$name}";
        }
        if (in_array('--factory', array_keys($options)) || ($options['--factory'] ?? false)) {
            $output[] = "✓ Created factory: database/factories/{$name}Factory.php";
        }
        if (in_array('--seed', array_keys($options)) || ($options['--seed'] ?? false)) {
            $output[] = "✓ Created seeder: database/seeders/{$name}Seeder.php";
        }

        $output[] = '';
        $output[] = 'Suggested model code:';
        $output[] = '```php';
        $output[] = $this->generateModelCode($name, $parsedFields, $parsedRelationships);
        $output[] = '```';

        if (count($parsedFields) > 0) {
            $output[] = '';
            $output[] = 'Suggested migration fields:';
            $output[] = '```php';
            $output[] = $this->generateMigrationFields($parsedFields);
            $output[] = '```';
        }

        return implode("\n", $output);
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool}>|string
     */
    private function parseFields(string $fields): array|string
    {
        $parsed = [];
        $fieldList = array_filter(array_map('trim', explode(',', $fields)));

        foreach ($fieldList as $field) {
            $parts = explode(':', $field);
            if (count($parts) < 2) {
                return "Error: Invalid field format '{$field}'. Use 'name:type' format.";
            }

            $parsed[] = [
                'name' => $parts[0],
                'type' => $parts[1],
                'nullable' => in_array('nullable', array_slice($parts, 2), true),
            ];
        }

        return $parsed;
    }

    /**
     * @return array<int, array{type: string, model: string}>|string
     */
    private function parseRelationships(string $relationships): array|string
    {
        $parsed = [];
        $relationList = array_filter(array_map('trim', explode(',', $relationships)));

        foreach ($relationList as $relation) {
            $parts = explode(':', $relation);
            if (count($parts) !== 2) {
                return "Error: Invalid relationship format '{$relation}'. Use 'type:Model' format.";
            }

            $validTypes = ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphTo', 'morphMany'];
            if (! in_array($parts[0], $validTypes, true)) {
                return "Error: Unknown relationship type '{$parts[0]}'. Valid types: ".implode(', ', $validTypes);
            }

            $parsed[] = [
                'type' => $parts[0],
                'model' => $parts[1],
            ];
        }

        return $parsed;
    }

    /**
     * @return array<string, bool>
     */
    private function buildArtisanOptions(string $with): array
    {
        return match ($with) {
            'migration' => ['--migration' => true],
            'factory' => ['--factory' => true],
            'seeder' => ['--seed' => true],
            'all' => ['--migration' => true, '--factory' => true, '--seed' => true],
            default => [],
        };
    }

    /**
     * @param  array<int, array{name: string, type: string, nullable: bool}>  $fields
     * @param  array<int, array{type: string, model: string}>  $relationships
     */
    private function generateModelCode(string $name, array $fields, array $relationships): string
    {
        $fillable = array_map(fn ($f) => "'{$f['name']}'", $fields);
        $casts = $this->generateCasts($fields);

        $code = <<<PHP
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Database\Eloquent\Model;

        class {$name} extends Model
        {
            use HasFactory;

            protected \$fillable = [
                {$this->formatArrayItems($fillable)}
            ];

        PHP;

        if (count($casts) > 0) {
            $code .= <<<PHP

            protected function casts(): array
            {
                return [
                    {$this->formatArrayItems($casts)}
                ];
            }

        PHP;
        }

        foreach ($relationships as $rel) {
            $methodName = lcfirst($rel['model']);
            if ($rel['type'] === 'hasMany' || $rel['type'] === 'belongsToMany' || $rel['type'] === 'morphMany') {
                $methodName = str($methodName)->plural()->toString();
            }

            $code .= <<<PHP

            public function {$methodName}()
            {
                return \$this->{$rel['type']}({$rel['model']}::class);
            }

        PHP;
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * @param  array<int, array{name: string, type: string, nullable: bool}>  $fields
     * @return array<string>
     */
    private function generateCasts(array $fields): array
    {
        $casts = [];
        $castMap = [
            'boolean' => "'boolean'",
            'integer' => "'integer'",
            'float' => "'float'",
            'decimal' => "'decimal:2'",
            'date' => "'date'",
            'datetime' => "'datetime'",
            'timestamp' => "'datetime'",
            'json' => "'array'",
        ];

        foreach ($fields as $field) {
            if (isset($castMap[$field['type']])) {
                $casts[] = "'{$field['name']}' => {$castMap[$field['type']]}";
            }
        }

        return $casts;
    }

    /**
     * @param  array<int, array{name: string, type: string, nullable: bool}>  $fields
     */
    private function generateMigrationFields(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            $method = $this->getSchemaMethod($field['type']);
            $line = "\$table->{$method}('{$field['name']}')";
            if ($field['nullable']) {
                $line .= '->nullable()';
            }
            $line .= ';';
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function getSchemaMethod(string $type): string
    {
        return match ($type) {
            'int', 'integer' => 'integer',
            'bigint' => 'bigInteger',
            'float' => 'float',
            'decimal' => 'decimal',
            'bool', 'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'time' => 'time',
            'text' => 'text',
            'longtext' => 'longText',
            'json' => 'json',
            'uuid' => 'uuid',
            default => 'string',
        };
    }

    /**
     * @param  array<string>  $items
     */
    private function formatArrayItems(array $items): string
    {
        if (count($items) === 0) {
            return '';
        }

        return implode(",\n        ", $items).',';
    }
}
