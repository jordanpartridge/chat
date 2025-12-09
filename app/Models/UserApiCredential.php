<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserApiCredential extends Model
{
    /** @use HasFactory<\Database\Factories\UserApiCredentialFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'last_used_at',
        'is_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'last_used_at' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * Scope a query to only include credentials for a specific provider.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<UserApiCredential>  $query
     */
    public function scopeForProvider($query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /**
     * Get the masked API key showing only the last 4 characters.
     */
    protected function maskedKey(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->api_key ? 'sk-••••••'.substr($this->api_key, -4) : null,
        );
    }

    /**
     * Check if the credential is configured.
     */
    protected function isConfigured(): Attribute
    {
        return Attribute::make(
            get: fn () => ! empty($this->api_key),
        );
    }
}
