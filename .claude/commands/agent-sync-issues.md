# Agent Sync GitHub Issues

Pull GitHub issues and convert them to agent tasks.

## Process

### Step 1: Load Config
```bash
cat .claude/agent/config.yaml  # Get sync_labels, ignore_labels
```

### Step 2: Fetch Issues
```bash
gh issue list --state open --label agent --json number,title,body,labels --limit 20
```

### Step 3: Load Current Queue
```bash
cat .claude/agent/queue.json
```

### Step 4: Filter & Convert
For each issue NOT already in queue (check by `gh-<number>` id):

Infer type from labels:
- "bug" → fix
- "enhancement", "feature" → feature  
- "documentation" → docs
- "refactor" → refactor
- "test" → test
- Default → feature

Infer priority:
- "priority:critical", "urgent" → 1
- "priority:high" → 2
- "priority:medium", no priority → 3
- "priority:low" → 4

Create task:
```json
{
  "id": "gh-<number>",
  "type": "<inferred>",
  "priority": <inferred>,
  "spec": "GitHub Issue #<number>: <title>\n\n<body>\n\nClose with: fixes #<number>",
  "status": "pending",
  "github_issue": <number>,
  "created_at": "<now>"
}
```

### Step 5: Update Queue
Add new tasks to `.claude/agent/queue.json`
Update `last_sync` timestamp

### Step 6: Report
```
[SYNC] Fetched 5 issues with 'agent' label
[SYNC] Skipping #3 (already queued)
[SYNC] Added 4 tasks:
  - gh-1: Add markdown rendering (feature, p3)
  - gh-2: Add chat settings (feature, p3)
  - gh-4: Add artifact rendering (feature, p3)
  - gh-5: Add AI providers (feature, p3)
[SYNC] Queue: 4 pending, 0 in progress
```
