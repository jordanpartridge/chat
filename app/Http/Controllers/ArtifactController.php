<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Chat;
use App\Services\SvgSanitizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ArtifactController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get all artifacts for a chat.
     */
    public function index(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $artifacts = Artifact::query()
            ->whereIn('message_id', $chat->messages()->pluck('id'))
            ->orderBy('created_at')
            ->get();

        return response()->json($artifacts);
    }

    /**
     * Get a specific artifact.
     */
    public function show(Request $request, Artifact $artifact): JsonResponse
    {
        $chat = $artifact->message?->chat;
        abort_if($chat === null, 404);
        $this->authorize('view', $chat);

        return response()->json($artifact);
    }

    /**
     * Render an artifact in a sandboxed iframe.
     */
    public function render(Request $request, Artifact $artifact): Response
    {
        $chat = $artifact->message?->chat;
        abort_if($chat === null, 404);
        $this->authorize('view', $chat);

        $view = $this->getRendererView($artifact);
        $html = $view->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Security-Policy' => $this->getCspHeader($artifact),
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Get the appropriate Blade view for the artifact type.
     */
    private function getRendererView(Artifact $artifact): View
    {
        $content = $artifact->content;

        // Sanitize SVG content to prevent XSS
        if ($artifact->type === Artifact::TYPE_SVG) {
            $content = app(SvgSanitizer::class)->sanitize($content);
        }

        $data = [
            'title' => $artifact->title,
            'content' => $content,
            'language' => $artifact->language ?? 'plaintext',
            'type' => $artifact->type,
        ];

        $viewName = match ($artifact->type) {
            Artifact::TYPE_CODE => 'artifacts.renderers.code',
            Artifact::TYPE_MARKDOWN => 'artifacts.renderers.markdown',
            Artifact::TYPE_HTML => 'artifacts.renderers.html',
            Artifact::TYPE_SVG => 'artifacts.renderers.svg',
            Artifact::TYPE_MERMAID => 'artifacts.renderers.mermaid',
            Artifact::TYPE_REACT => 'artifacts.renderers.react',
            Artifact::TYPE_VUE => 'artifacts.renderers.vue',
            default => 'artifacts.renderers.code',
        };

        return view($viewName, $data);
    }

    /**
     * Get CSP header based on artifact type.
     */
    private function getCspHeader(Artifact $artifact): string
    {
        $basePolicy = "default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;";

        return match ($artifact->type) {
            Artifact::TYPE_REACT,
            Artifact::TYPE_VUE => "{$basePolicy} script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com;",
            Artifact::TYPE_CODE,
            Artifact::TYPE_MARKDOWN,
            Artifact::TYPE_MERMAID => "{$basePolicy} script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;",
            Artifact::TYPE_SVG => "{$basePolicy} script-src 'none';",
            Artifact::TYPE_HTML => "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:;",
            default => $basePolicy,
        };
    }
}
