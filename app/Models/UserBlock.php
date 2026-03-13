<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blocker_id',
        'blocked_user_id',
        'reason',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
