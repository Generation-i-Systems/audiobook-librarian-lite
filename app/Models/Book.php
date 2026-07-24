<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stub model — lite server does not manage a book library.
 * Methods that call into this model will return null/empty.
 * Kept minimal, but retains HasFactory since sync/bookmark/badge
 * tests need to create Book rows as foreign-key targets.
 */
class Book extends Model
{
    use HasFactory;

    protected $table = 'books';
}
