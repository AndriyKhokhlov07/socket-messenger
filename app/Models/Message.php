<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'reply_to_message_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'attachment_type',
        'status',
        'delivered_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'attachment_size' => 'integer',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function scopeBetweenUsers(Builder $query, int $firstUserId, int $secondUserId): Builder
    {
        return $query->where(function (Builder $innerQuery) use ($firstUserId, $secondUserId) {
            $innerQuery->where('sender_id', $firstUserId)
                ->where('receiver_id', $secondUserId);
        })->orWhere(function (Builder $innerQuery) use ($firstUserId, $secondUserId) {
            $innerQuery->where('sender_id', $secondUserId)
                ->where('receiver_id', $firstUserId);
        });
    }
}
