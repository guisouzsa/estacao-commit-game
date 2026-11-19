<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProfileLink extends Model
{
    protected $fillable = ['platform','label','url','is_public','sort_order'];
    protected function casts(): array { return ['is_public'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
