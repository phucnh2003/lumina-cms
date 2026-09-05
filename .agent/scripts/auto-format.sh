#!/bin/bash
# PostToolUse hook script to format files after write_to_file or replace_file_content

PAYLOAD=$(cat)

# Extract TargetFile from toolCall arguments in payload
TARGET_FILE=$(echo "$PAYLOAD" | grep -o '"TargetFile":"[^"]*"' | head -n 1 | cut -d'"' -f4)

if [ -n "$TARGET_FILE" ] && [ -f "$TARGET_FILE" ]; then
    case "$TARGET_FILE" in
        *.ts|*.tsx|*.js|*.jsx|*.json|*.css)
            if command -v npx >/dev/null 2>&1; then
                npx prettier --write "$TARGET_FILE" >/dev/null 2>&1
            fi
            ;;
        *.php)
            if [ -f "./vendor/bin/pint" ]; then
                ./vendor/bin/pint "$TARGET_FILE" >/dev/null 2>&1
            fi
            ;;
    esac
fi

echo "{}"
