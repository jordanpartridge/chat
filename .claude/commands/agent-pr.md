# Agent Create PR

Create a pull request for completed GitHub issue work.

## Usage
```
/agent-pr [--task <task_id>]
```

## Process

### Step 1: Determine Task
If task_id provided, use it
Otherwise, find most recently completed GitHub-sourced task

### Step 2: Get Issue Details
```bash
gh issue view <issue_number> --json title,body,labels
```

### Step 3: Build PR
Title: `<type>: <issue title> (fixes #<number>)`

Body:
```markdown
## Summary
Automated implementation of #<issue_number>

## Changes
<list files changed from git diff --name-only main..HEAD>

## Testing
- [x] Pest tests pass
- [x] Pint formatting applied
- [x] npm build succeeds

Fixes #<issue_number>
```

### Step 4: Push & Create
```bash
git push -u origin HEAD
gh pr create --title "<title>" --body "<body>"
```

### Step 5: Report
```
[PR] Created PR #12: feat: Add markdown rendering (fixes #1)
[PR] URL: https://github.com/jordanpartridge/chat/pull/12
[PR] Linked to issue #1
```
