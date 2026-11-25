<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * "Líder": decidido SÓ no servidor. Vale para a conta cujo e-mail está em
     * PROFILE_ADMIN_EMAILS e já foi confirmado (código ou Google). Não existe coluna nem campo de formulário
     * que conceda isso, então ninguém consegue se atribuir a identificação pelo front ou por uma API.
     */
    public function isLeader(): bool
    {
        return $this->email_verified_at !== null
            && in_array(strtolower((string) $this->email), config('profiles.admin_emails'), true);
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->id ??= (string) Str::uuid7();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'display_name',
        'bio',
        'avatar',
        'profile_cover',
        'profile_frame',
        'profile_background',
        'primary_badge',
        'profile_title',
        'achievement_badge_id',
        'email',
        'google_id',
        'password',
        'password_set',
        'onboarding_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_code_hash',
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
            'email_code_expires_at' => 'datetime',
            'email_code_sent_at' => 'datetime',
            'email_code_attempts' => 'integer',
            'password' => 'hashed',
            'avatar_position_x' => 'integer',
            'avatar_position_y' => 'integer',
            'avatar_zoom' => 'integer',
            'cover_position_x' => 'integer',
            'cover_position_y' => 'integer',
            'onboarding_completed_at' => 'datetime',
            'password_set' => 'boolean',
            'matches_played' => 'integer',
            'matches_won' => 'integer',
            'matches_lost' => 'integer',
        ];
    }

    public function isGoogleOnly(): bool
    {
        return filled($this->google_id) && ! $this->password_set;
    }

    public function historicalGameStatistics(): ?array
    {
        if ($this->matches_played === null && $this->matches_won === null && $this->matches_lost === null) {
            return null;
        }

        return [
            'matches' => $this->matches_played,
            'wins' => $this->matches_won ?? 0,
            'losses' => $this->matches_lost ?? 0,
            'win_rate' => $this->matches_played
                ? round(($this->matches_won ?? 0) / $this->matches_played * 100, 1)
                : null,
        ];
    }

    public function badges(): BelongsToMany { return $this->belongsToMany(Badge::class)->withPivot(['unlocked_at','granted_by','progress']); }
    public function profileLinks(): HasMany { return $this->hasMany(ProfileLink::class)->orderBy('sort_order'); }
    public function achievementBadge(): BelongsTo { return $this->belongsTo(Badge::class, 'achievement_badge_id'); }
    public function roomParticipants(): HasMany { return $this->hasMany(RoomParticipant::class); }
}
