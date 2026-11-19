<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Badge extends Model
{
    protected $fillable = ['slug','name','description','category','rarity','image_path','active','secret','sort_order'];
    protected function casts(): array { return ['active'=>'boolean','secret'=>'boolean']; }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot(['unlocked_at','granted_by','progress']); }
}
