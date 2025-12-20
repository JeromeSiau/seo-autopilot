<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandVoice extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'tone',
        'vocabulary',
        'writing_style',
        'analyzed_from',
        'examples',
        'is_default',
    ];

    protected $casts = [
        'vocabulary' => 'array',
        'examples' => 'array',
        'is_default' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function setAsDefault(): void
    {
        // Remove default from other brand voices in the same team
        static::where('team_id', $this->team_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function toPromptContext(): string
    {
        $context = "Writing Style: {$this->writing_style}\n";
        $context .= "Tone: {$this->tone}\n";

        if (!empty($this->vocabulary)) {
            $context .= "Vocabulary preferences:\n";
            if (!empty($this->vocabulary['use'])) {
                $context .= "- Words to use: " . implode(', ', $this->vocabulary['use']) . "\n";
            }
            if (!empty($this->vocabulary['avoid'])) {
                $context .= "- Words to avoid: " . implode(', ', $this->vocabulary['avoid']) . "\n";
            }
        }

        if (!empty($this->examples)) {
            $context .= "\nExample excerpts from existing content:\n";
            foreach (array_slice($this->examples, 0, 3) as $example) {
                $context .= "---\n{$example}\n";
            }
        }

        return $context;
    }
}
