<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAssignment extends Model
{
    use HasFactory;

    public const ROLE_WRITER = 'writer';
    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_APPROVER = 'approver';

    public const ROLES = [
        self::ROLE_WRITER,
        self::ROLE_REVIEWER,
        self::ROLE_APPROVER,
    ];

    protected $fillable = [
        'article_id',
        'user_id',
        'role',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
