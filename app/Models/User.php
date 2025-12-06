<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Chat, $this>
     */
    public function chats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Agent, $this>
     */
    public function agents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<UserApiCredential, $this>
     */
    public function apiCredentials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserApiCredential::class);
    }

    /**
     * Get the API key for a specific provider.
     */
    public function getApiKeyFor(string $provider): ?string
    {
        return $this->apiCredentials()
            ->forProvider($provider)
            ->where('is_enabled', true)
            ->first()
            ?->api_key;
    }

    /**
     * Get all available AI models for this user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AiModel>
     */
    public function availableModels(): \Illuminate\Database\Eloquent\Collection
    {
        return AiModel::query()
            ->whereHas('credential', fn ($q) => $q
                ->where('user_id', $this->id)
                ->where('is_enabled', true)
            )
            ->where('enabled', true)
            ->with('credential:id,provider')
            ->get();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
