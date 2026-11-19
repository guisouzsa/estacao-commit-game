<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'primary_badge',
        'profile_title',
        'achievement_badge_id',
        'email',
        'google_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'avatar_position_x' => 'integer',
            'avatar_position_y' => 'integer',
            'avatar_zoom' => 'integer',
            'cover_position_x' => 'integer',
            'cover_position_y' => 'integer',
        ];
    }

    public function badges(): BelongsToMany { return $this->belongsToMany(Badge::class)->withPivot(['unlocked_at','granted_by','progress']); }
    public function profileLinks(): HasMany { return $this->hasMany(ProfileLink::class)->orderBy('sort_order'); }
    public function achievementBadge(): BelongsTo { return $this->belongsTo(Badge::class, 'achievement_badge_id'); }
}
