#!/bin/bash
# Run agent once on next pending task
# Usage: ./scripts/agent-once.sh

cd "$(dirname "$0")/.."

PENDING=$(jq '.tasks | map(select(.status == "pending")) | length' .claude/agent/queue.json 2>/dev/null || echo "0")

if [ "$PENDING" -eq 0 ]; then
    echo "[AGENT] No pending tasks"
    exit 0
fi

TASK=$(jq -r '.tasks | map(select(.status == "pending")) | sort_by(.priority) | .[0] | "\(.id) (\(.type), p\(.priority)): \(.spec | split("\n")[0])"' .claude/agent/queue.json)
echo "[AGENT] Running: $TASK"

claude -p "Execute /agent-run" \
    --allowedTools "Bash(php:*)" "Bash(npm:*)" "Bash(git:*)" "Bash(vendor/bin/pint:*)" "Read" "Write" "Edit" \
    --output-format text 2>&1 | tee ".claude/agent/logs/$(date +%Y%m%d_%H%M%S).log"
