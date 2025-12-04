<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artifact types supported:
 * - code: Programming code with syntax highlighting
 * - markdown: Markdown documents
 * - html: HTML documents for preview
 * - svg: SVG graphics
 * - mermaid: Mermaid diagrams
 * - react: React components (rendered in sandbox)
 */
class Artifact extends Model
{
    /** @use HasFactory<\Database\Factories\ArtifactFactory> */
    use HasFactory;

    use HasUuids;

    public const TYPE_CODE = 'code';

    public const TYPE_MARKDOWN = 'markdown';

    public const TYPE_HTML = 'html';

    public const TYPE_SVG = 'svg';

    public const TYPE_MERMAID = 'mermaid';

    public const TYPE_REACT = 'react';

    public const TYPE_VUE = 'vue';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_CODE,
        self::TYPE_MARKDOWN,
        self::TYPE_HTML,
        self::TYPE_SVG,
        self::TYPE_MERMAID,
        self::TYPE_REACT,
        self::TYPE_VUE,
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'message_id',
        'identifier',
        'type',
        'title',
        'language',
        'content',
        'version',
    ];

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isCode(): bool
    {
        return $this->type === self::TYPE_CODE;
    }

    public function isMarkdown(): bool
    {
        return $this->type === self::TYPE_MARKDOWN;
    }

    public function isHtml(): bool
    {
        return $this->type === self::TYPE_HTML;
    }

    public function isSvg(): bool
    {
        return $this->type === self::TYPE_SVG;
    }

    public function isMermaid(): bool
    {
        return $this->type === self::TYPE_MERMAID;
    }

    public function isReact(): bool
    {
        return $this->type === self::TYPE_REACT;
    }

    public function isVue(): bool
    {
        return $this->type === self::TYPE_VUE;
    }
}
