<?php

namespace App\Models;

use App\Traits\CamelCaseAttributeAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory;
    use CamelCaseAttributeAccess;
    use SoftDeletes;

    protected $fillable = [
        'book_id',
        'title',
        'author',
        'user_id',
        'comment',
        'age_rating',
        'content_rating',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'user_id' => 'integer',
        'age_rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
