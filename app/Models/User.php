<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\Models\WorkflowStatus;
use App\Enums\TeamMemberRole;
use App\Models\Concerns\HasNotifications;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\OwnerRole;
use Laravel\Jetstream\Role;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasNotifications;
    use HasProfilePhoto;
    use HasTeams;
    use LogsModelActivity;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

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
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'deleted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the first owned team used as the active-team fallback.
     */
    public function personalTeam(): ?Team
    {
        if (! $this->exists) {
            return null;
        }

        if ($this->relationLoaded('ownedTeams')) {
            /** @var Collection<int, Team> $ownedTeams */
            $ownedTeams = $this->getRelation('ownedTeams');

            return $ownedTeams->sortBy('id')->first();
        }

        return $this->ownedTeams()->oldest('id')->first();
    }

    /**
     * Get the role that the user has on the team.
     */
    public function teamRole($team): ?Role
    {
        if ($this->ownsTeam($team)) {
            return new OwnerRole();
        }

        if (! $this->belongsToTeam($team)) {
            return null;
        }

        $membershipRole = $team->users
            ->where('id', $this->id)
            ->first()
            ?->membership
            ?->role;

        $role = $membershipRole instanceof TeamMemberRole
            ? $membershipRole->value
            : $membershipRole;

        return $role ? Jetstream::findRole($role) : null;
    }

    /**
     * Determine if the user has the given role on the given team.
     */
    public function hasTeamRole($team, string $role): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $membershipRole = $team->users->where('id', $this->id)->first()
            ?->membership
            ?->role;

        $resolvedRole = $membershipRole instanceof TeamMemberRole
            ? $membershipRole->value
            : $membershipRole;

        return $resolvedRole === $role;
    }

    /**
     * Get the external provider links attached to the user.
     *
     * @return HasMany<ProviderAuth, $this>
     */
    public function providerAuths(): HasMany
    {
        return $this->hasMany(ProviderAuth::class);
    }

    /**
     * Get the persisted settings attached to the user.
     *
     * @return HasMany<UserSetting, $this>
     */
    public function userSettings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Get the canvases created by the user.
     *
     * @return HasMany<Canvas, $this>
     */
    public function canvases(): HasMany
    {
        return $this->hasMany(Canvas::class, 'created_by_user_id');
    }

    /**
     * Get the widgets created by the user.
     *
     * @return HasMany<Widget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class, 'created_by_user_id');
    }

    /**
     * Get the task items created by the user.
     *
     * @return HasMany<TaskItem, $this>
     */
    public function taskItems(): HasMany
    {
        return $this->hasMany(TaskItem::class, 'created_by_user_id');
    }

    public function workflowStoreFor(string $workflowClass): ?WorkflowStore
    {
        $team = $this->currentTeam;

        if ($team === null) {
            return null;
        }

        return $team->workflowStores()
            ->where('workflow_class', $workflowClass)
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->latest('id')
            ->first();
    }

    public function hasCompletedWorkflow(string $workflowClass, ?string $completionState = null): bool
    {
        $store = $this->workflowStoreFor($workflowClass);

        if ($store === null) {
            return false;
        }

        if ($store->status === WorkflowStatus::CLOSED) {
            return true;
        }

        if (! is_string($completionState) || $completionState === '') {
            return false;
        }

        return $store->workflow()->isState($completionState);
    }

    protected function activityEventMap(): array
    {
        return [
            'created' => ActivityEvent::UserCreated->value,
            'updated' => ActivityEvent::UserUpdated->value,
        ];
    }

    protected function activityLogAttributes(): array
    {
        return [
            'name',
            'email',
            'email_verified_at',
            'current_team_id',
        ];
    }
}
