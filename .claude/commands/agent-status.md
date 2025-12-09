# Agent Status Dashboard

Display current agent state and queue.

## Process

### Step 1: Load Data
```bash
cat .claude/agent/queue.json
cat .claude/agent/state.json
cat .claude/agent/config.yaml
```

### Step 2: Display Dashboard
```
╔══════════════════════════════════════════════════════╗
║                   CHAT AGENT STATUS                  ║
╠══════════════════════════════════════════════════════╣
║ Mode: once | Last Run: 2024-12-08 17:45:00          ║
║ Completed: 12 | Failed: 1 | GitHub Synced: Yes      ║
╠══════════════════════════════════════════════════════╣
║ PENDING (4)                                          ║
║ ┌──────────────────────────────────────────────────┐ ║
║ │ P1 gh-18  fix     Fix date parsing bug           │ ║
║ │ P2 gh-15  feature Add expense categories         │ ║
║ │ P3 gh-1   feature Add markdown rendering         │ ║
║ │ P4 gh-21  docs    Update README                  │ ║
║ └──────────────────────────────────────────────────┘ ║
╠══════════════════════════════════════════════════════╣
║ IN PROGRESS (0)                                      ║
╠══════════════════════════════════════════════════════╣
║ BLOCKED (1)                                          ║
║ ┌──────────────────────────────────────────────────┐ ║
║ │ gh-7  feature  Needs clarification on API design │ ║
║ └──────────────────────────────────────────────────┘ ║
╠══════════════════════════════════════════════════════╣
║ RECENT COMPLETED (3)                                 ║
║ ┌──────────────────────────────────────────────────┐ ║
║ │ ✅ gh-5  2024-12-08 15:30  Add AI providers      │ ║
║ │ ✅ gh-3  2024-12-08 14:15  Add model selector    │ ║
║ │ ✅ gh-2  2024-12-08 12:00  Add chat settings     │ ║
║ └──────────────────────────────────────────────────┘ ║
╚══════════════════════════════════════════════════════╝
```

### Step 3: Recommendations
If blocked tasks exist:
- Show what clarification is needed
- Suggest unblocking or removing

If queue empty:
- Suggest running /agent-sync-issues
