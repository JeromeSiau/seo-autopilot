<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPlanGeneration extends Model
{
    protected $fillable = [
        'site_id',
        'status',
        'current_step',
        'total_steps',
        'steps',
        'keywords_found',
        'articles_planned',
        'error_message',
    ];

    protected $casts = [
        'steps' => 'array',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'keywords_found' => 'integer',
        'articles_planned' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markStepRunning(int $stepIndex): void
    {
        $steps = $this->steps;
        $steps[$stepIndex]['status'] = 'running';
        $steps[$stepIndex]['started_at'] = now()->toIso8601String();

        $this->update([
            'current_step' => $stepIndex + 1,
            'steps' => $steps,
        ]);
    }

    public function markStepCompleted(int $stepIndex): void
    {
        $steps = $this->steps;
        $steps[$stepIndex]['status'] = 'completed';
        $steps[$stepIndex]['completed_at'] = now()->toIso8601String();

        $this->update(['steps' => $steps]);
    }

    public function markCompleted(int $keywordsFound, int $articlesPlanned): void
    {
        $this->update([
            'status' => 'completed',
            'keywords_found' => $keywordsFound,
            'articles_planned' => $articlesPlanned,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
