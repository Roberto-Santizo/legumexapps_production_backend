<?php

namespace App\Http\Controllers;

use App\Ai\Agents\WeeklyPlanTaskAssistant;
use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskAssistant\AskWeeklyPlanTaskAssistantRequest;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;

class WeeklyPlanTaskAssistantController extends Controller
{
    /**
     * Stream the assistant answer using the Vercel data protocol; the conversation id travels in the X-Conversation-Id header.
     *
     * The SDK only stores a new conversation after the stream ends, so it is created here first and the agent always continues it.
     */
    public function ask(AskWeeklyPlanTaskAssistantRequest $request, WeeklyPlanTaskAssistantServiceInterface $service, ConversationStore $conversationStore)
    {
        try {
            $data = $request->validated();
            $user = auth()->user();
            $conversationId = $data['conversation_id'] ?? null;

            if ($conversationId) {
                $service->assertConversationBelongsTo($user, $conversationId);
            } else {
                $conversationId = $conversationStore->storeConversation(
                    $user->getMorphClass(),
                    $user->getKey(),
                    Str::limit($data['message'], 100, preserveWords: true),
                );
            }

            $response = (new WeeklyPlanTaskAssistant)
                ->continue($conversationId, as: $user)
                ->stream(
                    $data['message'],
                    provider: config('services.weekly_plan_task_assistant.provider'),
                    model: config('services.weekly_plan_task_assistant.model') ?: null,
                )
                ->usingVercelDataProtocol()
                ->toResponse($request);

            $response->headers->set('X-Conversation-Id', $conversationId);

            return $response;
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
