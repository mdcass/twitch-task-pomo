<?php

namespace App\Models;

use App\Enums\ExternalAuthProvider;
use Database\Factories\StreamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stream extends Model
{
    /** @use HasFactory<StreamFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'provider_auth_id',
        'provider',
        'provider_channel_id',
        'channel_login',
        'display_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ExternalAuthProvider::class,
            'metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function providerAuth(): BelongsTo
    {
        return $this->belongsTo(ProviderAuth::class);
    }

    public function streamSessions(): HasMany
    {
        return $this->hasMany(StreamSession::class);
    }
}
