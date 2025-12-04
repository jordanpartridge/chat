<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModelName;
use App\Http\Requests\StoreChatRequest;
use App\Models\Chat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $chats = $request->user()->chats()->orderByDesc('updated_at')->get();

        return Inertia::render('Chat/Index', [
            'chats' => $chats,
            'models' => ModelName::getAvailableModels(),
        ]);
    }

    public function store(StoreChatRequest $request): RedirectResponse
    {
        $chat = $request->user()->chats()->create([
            'title' => str($request->validated('message'))->limit(50)->toString(),
            'model' => $request->validated('model'),
        ]);

        return to_route('chats.show', $chat);
    }

    /**
     * Display the specified chat and related data for the authenticated user.
     *
     * Aborts with a 403 response if the provided chat does not belong to the authenticated user.
     *
     * @param Request $request The incoming HTTP request (provides the authenticated user).
     * @param Chat $chat The chat to display; must belong to the authenticated user.
     * @return Response The Inertia response rendering the Chat/Show page with:
     *                  - `chat`: the chat loaded with its `messages`,
     *                  - `chats`: the user's chats ordered by `updated_at` descending,
     *                  - `models`: available model names from ModelName::getAvailableModels().
     */
    public function show(Request $request, Chat $chat): Response
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $chats = $request->user()->chats()->orderByDesc('updated_at')->get();

        return Inertia::render('Chat/Show', [
            'chat' => $chat->load('messages'),
            'chats' => $chats,
            'models' => ModelName::getAvailableModels(),
        ]);
    }

    /**
     * Update the given chat's model and title when provided.
     *
     * Validates optional `model` and `title` fields, applies the updates to the chat,
     * and redirects back to the previous page.
     *
     * @return \Illuminate\Http\RedirectResponse A redirect response back to the previous page.
     */
    public function update(Request $request, Chat $chat): RedirectResponse
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'model' => ['sometimes', 'string'],
            'title' => ['sometimes', 'string', 'max:255'],
        ]);

        $chat->update($validated);

        return back();
    }

    /**
     * Delete the specified chat if it belongs to the authenticated user and redirect to the chats index.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request (used for authenticated user).
     * @param \App\Models\Chat $chat The chat to delete.
     * @return \Illuminate\Http\RedirectResponse Redirect to the 'chats.index' route.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException If the authenticated user does not own the chat (HTTP 403).
     */
    public function destroy(Request $request, Chat $chat): RedirectResponse
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $chat->delete();

        return to_route('chats.index');
    }
}