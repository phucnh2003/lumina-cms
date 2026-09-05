#!/bin/bash
# PreToolUse hook script for run_command safety verification

PAYLOAD=$(cat)
COMMAND_LINE=$(echo "$PAYLOAD" | grep -o '"CommandLine":"[^"]*"' | head -n 1 | cut -d'"' -f4)

# Block high-risk destructive operations
if echo "$COMMAND_LINE" | grep -qE 'rm\s+-rf\s+(/|\$HOME|\~|\.git)'; then
    echo '{"decision": "deny", "reason": "Command blocked for safety: dangerous rm -rf operation detected."}'
    exit 0
fi

echo '{"decision": "allow"}'
