<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\ExternalAuthProvider;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\ProviderAuthFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Determine if the provider link is currently revoked.
     */
    public function isRevoked(): bool
    {
        return $this->trashed();
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
