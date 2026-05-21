#!/usr/bin/env bash

input=$(cat)

# Extract values from JSON input
cwd=$(echo "$input" | jq -r '.workspace.current_dir // .cwd // ""')
model=$(echo "$input" | jq -r '.model.display_name // ""')
used_pct=$(echo "$input" | jq -r '.context_window.used_percentage // empty')
session_pct=$(echo "$input" | jq -r '.rate_limits.five_hour.used_percentage // empty')
weekly_reset=$(echo "$input" | jq -r '.rate_limits.seven_day.resets_at // empty')

# Folder: show just the basename of the current directory
folder=$(basename "$cwd")

# Git branch: skip optional locks for safety
git_branch=""
if git -C "$cwd" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git_branch=$(git -C "$cwd" --no-optional-locks symbolic-ref --short HEAD 2>/dev/null)
fi

# Build progress bar for context usage (20 chars wide)
build_bar() {
  local pct="$1"
  local width=20
  local filled=$(echo "$pct $width" | awk '{printf "%d", ($1/100)*$2}')
  local empty=$((width - filled))
  local bar=""
  local i
  for ((i=0; i<filled; i++)); do bar="${bar}#"; done
  for ((i=0; i<empty; i++)); do bar="${bar}-"; done
  printf "%s" "$bar"
}

# ANSI colors
RESET="\033[0m"
BOLD="\033[1m"
CYAN="\033[36m"
YELLOW="\033[33m"
GREEN="\033[32m"
MAGENTA="\033[35m"
BLUE="\033[34m"
RED="\033[31m"

# Pick a color from usage % (green < 50, yellow < 80, red ≥ 80)
pct_color() {
  local p="$1"
  if [ "$p" -ge 80 ]; then
    printf "%s" "$RED"
  elif [ "$p" -ge 50 ]; then
    printf "%s" "$YELLOW"
  else
    printf "%s" "$GREEN"
  fi
}

# Folder segment
printf "📁 ${BOLD}${CYAN}%s${RESET}" "$folder"

# Git branch segment
if [ -n "$git_branch" ]; then
  printf " 🌿 ${YELLOW}(%s)${RESET}" "$git_branch"
fi

# Model segment
if [ -n "$model" ]; then
  printf " 🤖 ${MAGENTA}[%s]${RESET}" "$model"
fi

# Context (brain = Claude's working memory)
if [ -n "$used_pct" ]; then
  bar=$(build_bar "$used_pct")
  pct_int=$(printf "%.0f" "$used_pct")
  bar_color=$(pct_color "$pct_int")
  printf " 🧠 ${bar_color}[%s]${RESET}${BLUE}%d%%${RESET}" "$bar" "$pct_int"
fi

# 5-hour session usage (Pro/Max only — silently skipped otherwise)
if [ -n "$session_pct" ]; then
  bar=$(build_bar "$session_pct")
  pct_int=$(printf "%.0f" "$session_pct")
  bar_color=$(pct_color "$pct_int")
  printf " ⏳🤖 ${bar_color}[%s]${RESET}${BLUE}%d%%${RESET}" "$bar" "$pct_int"
fi

# 7-day rolling: show reset day instead of % (Pro/Max only)
if [ -n "$weekly_reset" ]; then
  reset_day=$(date -r "$weekly_reset" '+%a %d %b' 2>/dev/null)
  if [ -n "$reset_day" ]; then
    printf " 📅 ${BLUE}reset %s${RESET}" "$reset_day"
  fi
fi
