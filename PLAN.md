# Model Configuration: Database-Driven with Auto-Sync

## Overview

Replace the dual `ModelName` enum + `AiModel` database system with a single database-driven approach that auto-syncs models from providers before selection.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     ModelSyncService                            │
│  - syncOllamaModels() - queries Ollama API, upserts to DB       │
│  - syncGroqModels() - checks API key, enables/disables in DB    │
│  - syncAll() - runs all provider syncs                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    AiModel (Database)                           │
│  - Single source of truth for all model config                  │
│  - Columns: provider, model_id, name, description,              │
│    context_window, supports_tools, supports_vision,             │
│    speed_tier, cost_tier, is_available, enabled                 │
│  - Scopes: available(), enabled(), byProvider()                 │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
        ┌──────────┐   ┌──────────┐   ┌──────────────┐
        │   Chat   │   │  Agent   │   │  Frontend    │
        │ ai_model │   │ models   │   │  (selector)  │
        │   _id FK │   │  M:M     │   │              │
        └──────────┘   └──────────┘   └──────────────┘
```

## Implementation Steps

### Phase 1: Database Schema Updates

1. **Migration: Add columns to `ai_models`**
   - Add `is_available` boolean (runtime availability from provider)
   - Add `description` text column
   - Keep `enabled` as admin override

2. **Migration: Update `chats` table**
   - Add `ai_model_id` foreign key (nullable initially)
   - Keep `model` column temporarily for migration

3. **Seeder: Populate `ai_models`**
   - Seed all 9 current models from enum data
   - Include metadata: context_window, supports_tools, supports_vision, tiers

### Phase 2: ModelSyncService

Create `App\Services\ModelSyncService`:

```php
class ModelSyncService
{
    public function syncAll(): void;
    public function syncOllama(): void;  // Query API, update is_available
    public function syncGroq(): void;    // Check API key, update is_available
    public function getAvailableModels(): Collection;
}
```

**Sync Logic:**
- Ollama: Call `/api/tags`, match against `ai_models` where provider=ollama
- Groq: Check `config('prism.providers.groq.api_key')` exists
- Future: OpenAI, Anthropic, etc.

### Phase 3: Update AiModel

Enhance `App\Models\AiModel`:

```php
// New scopes
public function scopeAvailable(Builder $query): Builder;

// Helper methods
public function getPrismProvider(): Provider;
public function toFrontendArray(): array;

// Static helpers
public static function findByModelId(string $modelId): ?self;
public static function getDefaultModel(): self;
```

### Phase 4: Update Controllers

**ChatController:**
```php
// Before returning models to frontend, sync first
public function index(Request $request): Response
{
    app(ModelSyncService::class)->syncAll();
    
    return Inertia::render('Chat/Index', [
        'models' => AiModel::available()->enabled()->get()->map->toFrontendArray(),
    ]);
}
```

**ChatStreamController:**
- Accept `ai_model_id` instead of model string
- Load AiModel, use `getPrismProvider()` and `model_id`

### Phase 5: Update Services

**ChatStreamService:**
- Change signature: `stream(Chat $chat, string $userMessage, AiModel $model)`
- Use `$model->getPrismProvider()` and `$model->model_id`
- Check `$model->supports_tools` instead of enum method

**GenerateChatTitle job:**
- Load AiModel from chat relationship
- Use model's provider/id

### Phase 6: Update Form Requests

**StoreChatRequest & ChatStreamRequest:**
- Validate `ai_model_id` exists in `ai_models` table
- Remove `Rule::enum(ModelName::class)`

### Phase 7: Update Chat Model

```php
class Chat extends Model
{
    protected $fillable = ['user_id', 'title', 'ai_model_id'];
    
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }
}
```

### Phase 8: Data Migration

Create migration to:
1. Map existing `chats.model` strings to `ai_models.id`
2. Update `chats.ai_model_id` 
3. Drop `chats.model` column

### Phase 9: Frontend Updates

**TypeScript types (`chat.ts`):**
```typescript
export interface AiModel {
    id: number;
    name: string;
    description: string;
    provider: string;
    model_id: string;
    supports_tools: boolean;
    supports_vision: boolean;
    context_window: number;
    speed_tier: string;
    cost_tier: string;
}

export interface Chat {
    id: string;
    ai_model_id: number;
    ai_model?: AiModel;
    // ...
}
```

**Components:**
- Update model selector to use `ai_model.id`
- Update chat display to show `ai_model.name`

### Phase 10: Cleanup

1. Delete `app/Enums/ModelName.php`
2. Delete `tests/Feature/Enums/ModelNameTest.php`
3. Update `ChatFactory` to use `AiModel::factory()`
4. Update all tests that reference `ModelName`

### Phase 11: Tests

New tests:
- `ModelSyncServiceTest` - sync logic, availability detection
- `AiModelTest` - scopes, helpers, provider mapping
- Update `ChatControllerTest`, `ChatStreamControllerTest`

## Files to Create

| File | Purpose |
|------|---------|
| `app/Services/ModelSyncService.php` | Provider sync orchestration |
| `database/migrations/xxx_add_columns_to_ai_models.php` | Schema updates |
| `database/migrations/xxx_add_ai_model_id_to_chats.php` | FK addition |
| `database/migrations/xxx_migrate_chat_models.php` | Data migration |
| `database/seeders/AiModelSeeder.php` | Initial model data |
| `tests/Feature/Services/ModelSyncServiceTest.php` | Sync tests |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Models/AiModel.php` | Add scopes, helpers, provider mapping |
| `app/Models/Chat.php` | Add `aiModel()` relationship, update fillable |
| `app/Services/ChatStreamService.php` | Use AiModel instead of enum |
| `app/Http/Controllers/ChatController.php` | Sync + query AiModel |
| `app/Http/Controllers/ChatStreamController.php` | Load AiModel |
| `app/Http/Requests/StoreChatRequest.php` | Validate ai_model_id |
| `app/Http/Requests/ChatStreamRequest.php` | Validate ai_model_id |
| `app/Jobs/GenerateChatTitle.php` | Use Chat->aiModel relationship |
| `database/factories/ChatFactory.php` | Use AiModel factory |
| `resources/js/types/chat.ts` | Update types |
| Vue components | Update model references |

## Files to Delete

| File | Reason |
|------|--------|
| `app/Enums/ModelName.php` | Replaced by database |
| `tests/Feature/Enums/ModelNameTest.php` | No longer needed |

## Auto-Sync Strategy

**When to sync:**
1. Before rendering chat index/show pages (user is selecting model)
2. Cache sync results for 60 seconds to avoid hammering APIs
3. Background job option for periodic refresh

**Sync behavior:**
- Ollama: Set `is_available = true` for installed models
- Groq: Set `is_available = config('prism.providers.groq.api_key') !== null`
- Models not found: Set `is_available = false` (don't delete - preserves history)

## Benefits

1. **Single source of truth** - No enum/DB duplication
2. **Auto-sync** - Models appear/disappear based on actual availability
3. **Rich metadata** - context_window, vision, speed/cost tiers in DB
4. **Admin control** - `enabled` flag for manual override
5. **Agent integration** - Works with existing agent_model pivot
6. **Extensible** - Easy to add OpenAI, Anthropic, etc.
7. **History preservation** - Old chats keep their model reference
