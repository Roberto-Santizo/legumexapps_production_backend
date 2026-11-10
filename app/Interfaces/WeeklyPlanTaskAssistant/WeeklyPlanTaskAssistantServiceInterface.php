<?php

namespace App\Interfaces\WeeklyPlanTaskAssistant;

use App\Enums\CaptureType;
use App\Models\User;
use App\Models\WeeklyPlanTask;
use Illuminate\Support\Collection;

interface WeeklyPlanTaskAssistantServiceInterface
{
    /**
     * @param  array{weekly_plan_id?: ?int, week?: ?int, year?: ?int, operation_date_from?: ?string, operation_date_to?: ?string, line_code?: ?string, sku_code?: ?string, status?: ?int}  $filters
     * @return Collection<int, WeeklyPlanTask>
     */
    public function searchTasks(array $filters): Collection;

    public function getTaskDetail(string $taskId): WeeklyPlanTask;

    public function getTaskCaptureFields(string $taskId): Collection;

    public function getTaskEmployees(string $taskId): Collection;

    public function getTaskTimeouts(string $taskId): Collection;

    public function getTaskLogs(string $taskId): Collection;

    public function getTaskObservations(string $taskId): Collection;

    public function getCaptureRecords(string $taskId, CaptureType $captureType): Collection;

    /**
     * @param  array<string, mixed>  $scope  task_id or task filters
     * @return array<string, mixed>
     */
    public function summarizeCaptureRecords(CaptureType $captureType, array $scope): array;

    public function assertConversationBelongsTo(User $user, string $conversationId): void;

    public function getConversations(User $user, ?string $limit);

    public function getConversationMessages(User $user, string $conversationId);

    public function deleteConversation(User $user, string $conversationId): bool;
}
