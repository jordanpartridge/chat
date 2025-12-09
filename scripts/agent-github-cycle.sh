#!/bin/bash
# Full GitHub → Agent → PR cycle
# Usage: ./scripts/agent-github-cycle.sh

set -e
cd "$(dirname "$0")/.."

echo "[CYCLE] ========================================"
echo "[CYCLE] GitHub Agent Cycle - $(date '+%Y-%m-%d %H:%M')"
echo "[CYCLE] ========================================"

# Step 1: Sync issues
echo ""
echo "[CYCLE] Step 1: Syncing GitHub issues..."
claude -p "Execute /agent-sync-issues - follow .claude/commands/agent-sync-issues.md exactly" \
    --allowedTools "Bash(gh:*)" "Read" "Write" "Edit" \
    --output-format text 2>&1 | tail -15 || true

# Step 2: Check queue
PENDING=$(jq '.tasks | map(select(.status == "pending")) | length' .claude/agent/queue.json 2>/dev/null || echo "0")
echo ""
echo "[CYCLE] Step 2: Queue has $PENDING pending task(s)"

if [ "$PENDING" -eq 0 ]; then
    echo "[CYCLE] Nothing to do. Exiting."
    exit 0
fi

# Step 3: Get next task
TASK_ID=$(jq -r '.tasks | map(select(.status == "pending")) | sort_by(.priority) | .[0].id' .claude/agent/queue.json)
TASK_TYPE=$(jq -r '.tasks | map(select(.status == "pending")) | sort_by(.priority) | .[0].type' .claude/agent/queue.json)
echo "[CYCLE] Next: $TASK_ID ($TASK_TYPE)"

# Step 4: Branch for GitHub issues
if [[ "$TASK_ID" == gh-* ]]; then
    ISSUE_NUM=${TASK_ID#gh-}
    BRANCH="$TASK_TYPE/issue-$ISSUE_NUM"
    echo "[CYCLE] Creating branch: $BRANCH"
    git checkout main 2>/dev/null || git checkout master
    git pull
    git checkout -b "$BRANCH" 2>/dev/null || git checkout "$BRANCH"
fi

# Step 5: Run agent
echo ""
echo "[CYCLE] Step 3: Running agent..."
claude -p "Execute /agent-run - follow .claude/commands/agent-run.md exactly" \
    --allowedTools "Bash(php:*)" "Bash(npm:*)" "Bash(git:*)" "Bash(vendor/bin/pint:*)" "Read" "Write" "Edit" \
    --output-format text 2>&1 | tail -30 || true

# Step 6: Check completion & PR
sleep 2
STATUS=$(jq -r --arg id "$TASK_ID" '.tasks[] | select(.id == $id) | .status' .claude/agent/queue.json 2>/dev/null || echo "unknown")
COMPLETED=$(jq -r --arg id "$TASK_ID" '.completed[] | select(.id == $id) | .id' .claude/agent/queue.json 2>/dev/null || true)

if [ -n "$COMPLETED" ] && [[ "$TASK_ID" == gh-* ]]; then
    echo ""
    echo "[CYCLE] Step 4: Creating PR..."
    git push -u origin HEAD 2>&1 || true
    gh pr create --fill 2>&1 || echo "[CYCLE] PR may already exist"
    git checkout main 2>/dev/null || git checkout master
    echo "[CYCLE] ✅ Complete!"
elif [ "$STATUS" = "blocked" ]; then
    echo "[CYCLE] ⚠️  Task blocked - needs human input"
else
    echo "[CYCLE] Task still in progress"
fi

echo ""
echo "[CYCLE] ========================================"
