<?php

namespace App\Http\Controllers;

use App\Ai\Agents\WeeklyPlanTaskAssistant;
use App\Helpers\ResponseHandler;
use App\Http\Requests\WeeklyPlanTaskAssistant\AskWeeklyPlanTaskAssistantRequest;
use App\Http\Resources\WeeklyPlanTaskAssistant\ConversationMessageResource;
use App\Http\Resources\WeeklyPlanTaskAssistant\ConversationResource;
use App\Http\Resources\WeeklyPlanTaskAssistant\PaginatedConversationsResource;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use Illuminate\Http\Request;
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

    public function conversations(Request $request, WeeklyPlanTaskAssistantServiceInterface $service)
    {
        try {
            $limit = $request->query('limit');
            $conversations = $service->getConversations(auth()->user(), $limit);
            $data = $limit ? new PaginatedConversationsResource($conversations) : ConversationResource::collection($conversations);

            return ResponseHandler::success($data, 'Conversaciones Obtenidas Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function messages(string $id, WeeklyPlanTaskAssistantServiceInterface $service)
    {
        try {
            $messages = $service->getConversationMessages(auth()->user(), $id);

            return ResponseHandler::success(ConversationMessageResource::collection($messages), 'Mensajes Obtenidos Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }

    public function deleteConversation(string $id, WeeklyPlanTaskAssistantServiceInterface $service)
    {
        try {
            $service->deleteConversation(auth()->user(), $id);

            return ResponseHandler::success(null, 'Conversación Eliminada Correctamente', 200);
        } catch (\Throwable $th) {
            return ResponseHandler::error($th);
        }
    }
}
