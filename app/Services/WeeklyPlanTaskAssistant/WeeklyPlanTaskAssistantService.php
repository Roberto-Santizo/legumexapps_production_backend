<?php

namespace App\Services\WeeklyPlanTaskAssistant;

use App\Calculators\CaptureSummaryCalculator;
use App\Enums\CaptureType;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\User;
use App\Models\WeeklyPlanTask;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\ConversationStore;
use Override;

class WeeklyPlanTaskAssistantService implements WeeklyPlanTaskAssistantServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private CaptureSummaryCalculator $captureSummaryCalculator,
        private ConversationStore $conversationStore,
    ) {}

    #[Override]
    public function searchTasks(array $filters): Collection
    {
        return collect();
    }

    #[Override]
    public function getTaskDetail(string $taskId): WeeklyPlanTask
    {
        return $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
    }

    #[Override]
    public function getTaskCaptureFields(string $taskId): Collection
    {
        return collect();
    }

    #[Override]
    public function getTaskEmployees(string $taskId): Collection
    {
        return collect();
    }

    #[Override]
    public function getTaskTimeouts(string $taskId): Collection
    {
        return collect();
    }

    #[Override]
    public function getTaskLogs(string $taskId): Collection
    {
        return collect();
    }

    #[Override]
    public function getTaskObservations(string $taskId): Collection
    {
        return collect();
    }

    #[Override]
    public function getCaptureRecords(string $taskId, CaptureType $captureType): Collection
    {
        return collect();
    }

    #[Override]
    public function summarizeCaptureRecords(CaptureType $captureType, array $scope): array
    {
        return [];
    }

    #[Override]
    public function assertConversationBelongsTo(User $user, string $conversationId): void
    {
        //
    }

    #[Override]
    public function getConversations(User $user, ?string $limit)
    {
        return collect();
    }

    #[Override]
    public function getConversationMessages(User $user, string $conversationId)
    {
        return collect();
    }

    #[Override]
    public function deleteConversation(User $user, string $conversationId): bool
    {
        return false;
    }
}
