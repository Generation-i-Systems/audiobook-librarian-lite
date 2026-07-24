<?php

namespace App\Models;

use App\Traits\CamelCaseAttributeAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBookStatus extends Model
{
    use CamelCaseAttributeAccess;
    use HasFactory;

    protected $table = 'user_book_status';

    protected $fillable = [
        'user_id',
        'playlist_id',
        'book_id',
        'title',
        'author',
        'order',
        'status',
        'status_detail',
        'read_count',
        'target_date',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'playlist_id' => 'integer',
        'order' => 'integer',
        'status_detail' => 'array',
        'read_count' => 'integer',
        'target_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }
}
