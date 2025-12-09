# Agent Run - Execute Next Task

Autonomous task execution for Laravel/Inertia/Vue codebase.

## Process

### Step 1: Load State
```bash
cat .claude/agent/queue.json
cat .claude/agent/config.yaml
```

### Step 2: Select Task
- Find highest priority task with status "pending"
- If no pending tasks, report "Queue empty" and exit
- Update task status to "in_progress"

### Step 3: Execute Task
Based on task type:

**feature**: 
1. Understand the spec fully
2. Create/modify files following Laravel conventions
3. If Vue components: follow existing patterns in resources/js/
4. Run `vendor/bin/pint --dirty` for PHP files
5. Run `npm run build` for frontend changes
6. Write/update Pest tests
7. Run `php artisan test --filter=<relevant>`

**fix**:
1. Locate the bug
2. Write failing test first
3. Fix the code
4. Verify test passes
5. Run pint

**refactor**:
1. Ensure tests exist for affected code
2. Refactor incrementally
3. Run tests after each change

**test**:
1. Write comprehensive tests
2. Cover happy path, edge cases, failures

### Step 4: Verify
- All tests pass: `php artisan test`
- No lint errors: `vendor/bin/pint --dirty`
- Frontend builds: `npm run build`

### Step 5: Commit
```bash
git add -A
git commit -m "<type>: <description>

<detailed changes>

Task: <task_id>
fixes #<issue_number>"  # If GitHub issue
```

### Step 6: Update Queue
Move task from `tasks` to `completed` with:
- status: "complete" | "blocked" | "failed"
- completed_at: timestamp
- output: summary of changes
- files_changed: list of files

### Blocking Conditions
Set status to "blocked" if:
- Spec is ambiguous - need human clarification
- Requires migration (blocked_paths)
- Requires package changes
- Tests fail after 3 attempts
- Timeout approaching

## Output
```
[AGENT] Task: gh-1 (feature)
[AGENT] Spec: Add markdown rendering for chat messages
[AGENT] Installing marked...
[AGENT] Updating ChatMessage.vue...
[AGENT] Writing test...
[AGENT] Tests passing (12 assertions)
[AGENT] Committed: feat: add markdown rendering (fixes #1)
[AGENT] ✅ Task complete
```
