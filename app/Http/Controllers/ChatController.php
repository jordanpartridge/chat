<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Http\Requests\UpdateChatRequest;
use App\Models\Agent;
use App\Models\Chat;
use App\Services\ModelSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly ModelSyncService $modelSyncService
    ) {}

    public function index(Request $request): Response
    {
        $chats = $request->user()->chats()->orderByDesc('updated_at')->get();
        $agents = $this->getAvailableAgents($request);

        return Inertia::render('Chat/Index', [
            'chats' => $chats,
            'models' => $this->modelSyncService->syncAndGetAvailable(),
            'agents' => $agents,
        ]);
    }

    public function store(StoreChatRequest $request): RedirectResponse
    {
        $chat = $request->user()->chats()->create([
            'title' => str($request->validated('message'))->limit(50)->toString(),
            'ai_model_id' => $request->validated('ai_model_id'),
            'agent_id' => $request->validated('agent_id'),
        ]);

        return to_route('chats.show', $chat);
    }

    public function show(Request $request, Chat $chat): Response
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $chats = $request->user()->chats()->orderByDesc('updated_at')->get();
        $agents = $this->getAvailableAgents($request);

        return Inertia::render('Chat/Show', [
            'chat' => $chat->load(['messages', 'agent']),
            'chats' => $chats,
            'models' => $this->modelSyncService->syncAndGetAvailable(),
            'agents' => $agents,
        ]);
    }

    public function update(UpdateChatRequest $request, Chat $chat): RedirectResponse
    {
        $chat->update($request->validated());

        return back();
    }

    public function destroy(Request $request, Chat $chat): RedirectResponse
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $chat->delete();

        return to_route('chats.index');
    }

    /**
     * Get agents available to the current user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Agent>
     */
    private function getAvailableAgents(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        return Agent::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->active()
            ->with('defaultModel')
            ->orderBy('name')
            ->get();
    }
}
