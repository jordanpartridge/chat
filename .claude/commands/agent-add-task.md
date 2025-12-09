# Agent Add Task

Manually add a task to the queue.

## Usage
```
/agent-add-task <type> <priority> <spec>
```

## Examples
```
/agent-add-task feature 2 "Add dark mode toggle to header"
/agent-add-task fix 1 "Chat messages not scrolling to bottom on new message"
/agent-add-task test 3 "Add tests for ChatMessage component"
```

## Process

### Step 1: Parse Arguments
- type: feature | fix | refactor | test | docs
- priority: 1-4 (1 = highest)
- spec: Description of the work

### Step 2: Generate Task ID
```
task-<timestamp>
```

### Step 3: Add to Queue
```json
{
  "id": "task-1733696400",
  "type": "<type>",
  "priority": <priority>,
  "spec": "<spec>",
  "status": "pending",
  "created_at": "<now>"
}
```

### Step 4: Confirm
```
[QUEUE] Added task-1733696400
[QUEUE] Type: feature | Priority: 2
[QUEUE] Spec: Add dark mode toggle to header
[QUEUE] Queue now has 5 pending tasks
```
