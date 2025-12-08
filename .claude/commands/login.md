---
description: Fast login - ensures user exists and logs in via Playwright
---

Execute these steps quickly with minimal output:

1. Run the login command to ensure user exists and get credentials:
```bash
php artisan login
```

2. Parse the output for URL, email, password. Navigate to the login URL with mcp__playwright__browser_navigate

3. Snapshot, fill, submit in rapid succession:
- mcp__playwright__browser_snapshot to get refs
- mcp__playwright__browser_type email field with the email from step 1
- mcp__playwright__browser_type password field with the password from step 1
- mcp__playwright__browser_click the login button

4. Confirm success with one final snapshot

Report only: "Logged in as {email} - now on [URL]"
