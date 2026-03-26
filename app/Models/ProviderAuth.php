<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\ExternalAuthProvider;
use App\Support\Database\JsonArrayContains;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\ProviderAuthFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderAuth extends Model
{
    /** @use HasFactory<ProviderAuthFactory> */
    use HasFactory;

    use LogsModelActivity;
    use SoftDeletes;

    protected static array $recordEvents = [
        'created',
        'updated',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'provider_user_id',
        'provider_email',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'profile',
        'last_used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => ExternalAuthProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'profile' => 'array',
            'last_used_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the provider authentication.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the streams authorized by the provider authentication.
     *
     * @return HasMany<Stream, $this>
     */
    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    /**
     * Determine if the provider link is currently revoked.
     */
    public function isRevoked(): bool
    {
        return $this->trashed();
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    public function hasRequiredScopes(array $requiredScopes): bool
    {
        $availableScopes = collect($this->scopes ?? [])
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values();

        return collect($requiredScopes)
            ->every(static fn (string $scope): bool => $availableScopes->contains($scope));
    }

    public function hasUsableTokenMaterial(): bool
    {
        return $this->token_expires_at === null
            || $this->token_expires_at->isFuture()
            || $this->refresh_token !== null;
    }

    public function isUsableFor(ExternalAuthProvider $provider): bool
    {
        return ! $this->isRevoked()
            && $this->hasRequiredScopes($provider->authScopes())
            && $this->hasUsableTokenMaterial();
    }

    public function scopeUsableFor(Builder $query, ExternalAuthProvider $provider): Builder
    {
        $query
            ->where($this->qualifyColumn('provider'), $provider->value)
            ->whereNull($this->qualifyColumn('deleted_at'))
            ->whereNotNull($this->qualifyColumn('access_token'))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull($this->qualifyColumn('token_expires_at'))
                    ->orWhere($this->qualifyColumn('token_expires_at'), '>', now())
                    ->orWhereNotNull($this->qualifyColumn('refresh_token'));
            });

        app(JsonArrayContains::class)->whereContainsAll(
            $query,
            $this->qualifyColumn('scopes'),
            $provider->authScopes(),
        );

        return $query;
    }

    protected function activityEventMap(): array
    {
        return [
            'created' => ActivityEvent::ProviderAuthCreated->value,
            'updated' => ActivityEvent::ProviderAuthUpdated->value,
        ];
    }

    protected function activityLogAttributes(): array
    {
        return [
            'provider',
            'provider_user_id',
            'provider_email',
            'avatar_url',
            'token_expires_at',
            'scopes',
            'last_used_at',
        ];
    }
}
