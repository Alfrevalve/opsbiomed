<?php

namespace App\Services\Compliance;

use App\Models\AiUsageLog;
use App\Models\User;
use InvalidArgumentException;

class AiUsageLogger
{
    public const SUGGESTION_LABEL = 'Sugerencia asistida';

    /**
     * @var list<string>
     */
    public const HUMAN_REVIEW_OUTCOMES = [
        'aceptada',
        'corregida',
        'descartada',
    ];

    public function record(
        string $module,
        string $action,
        string $inputType,
        bool $containsPersonalData = false,
        bool $containsHealthData = false,
        ?string $aiProvider = null,
        bool $humanReviewRequired = true,
        ?User $user = null,
    ): AiUsageLog {
        $actor = $user ?? auth()->user();

        return AiUsageLog::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'module' => $module,
            'action' => $action,
            'input_type' => $inputType,
            'contains_personal_data' => $containsPersonalData,
            'contains_health_data' => $containsHealthData,
            'ai_provider' => $aiProvider,
            'human_review_required' => $humanReviewRequired,
            'created_at' => now(),
        ]);
    }

    public function recordHumanReview(AiUsageLog $aiUsageLog, string $outcome, ?User $reviewer = null): AiUsageLog
    {
        if (! in_array($outcome, self::HUMAN_REVIEW_OUTCOMES, true)) {
            throw new InvalidArgumentException('El resultado de revision humana no es valido.');
        }

        $actor = $reviewer ?? auth()->user();
        $aiUsageLog->update([
            'human_reviewed_by' => $actor?->getAuthIdentifier(),
            'human_reviewed_at' => now(),
            'human_review_outcome' => $outcome,
        ]);

        return $aiUsageLog->refresh();
    }
}
