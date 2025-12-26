<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CostLog;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class CostTracker
{
    public static function log(
        Model $costable,
        string $type,
        string $provider,
        string $operation,
        float $cost,
        ?string $model = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        array $metadata = []
    ): CostLog {
        $teamId = self::resolveTeamId($costable);

        return CostLog::create([
            'costable_type' => $costable->getMorphClass(),
            'costable_id' => $costable->id,
            'team_id' => $teamId,
            'type' => $type,
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'cost' => $cost,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'metadata' => $metadata,
        ]);
    }

    private static function resolveTeamId(Model $costable): int
    {
        if ($costable instanceof Article) {
            return $costable->site->team_id;
        }

        if ($costable instanceof Site) {
            return $costable->team_id;
        }

        if ($costable instanceof Team) {
            return $costable->id;
        }

        if (method_exists($costable, 'team')) {
            return $costable->team->id;
        }

        throw new \InvalidArgumentException('Cannot resolve team_id from costable: ' . get_class($costable));
    }
}
