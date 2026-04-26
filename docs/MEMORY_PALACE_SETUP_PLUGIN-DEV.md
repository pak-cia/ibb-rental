# Memory Palace Setup Guide — WordPress Plugin Development

> **Status in this repo:** ✅ already in place. This repo follows the system described below — every component under `includes/` (and `templates/`) carries the four-doc set, the auto-commit hook is in `.claude/settings.json`, and `CLAUDE.md` lives at the plugin root. New contributors can read [`/CLAUDE.md`](../CLAUDE.md) to start working under it immediately. This file is preserved for **reference** so the system can be replicated on other plugin repos.

---

Adapted from the server infrastructure version for WordPress plugin development teams.
The core idea is identical: structured markdown that lets any Claude session orient itself
without asking. The adaptation changes the unit of organisation from "server service" to
"plugin component", and shifts the settings from a personal machine config to a file
committed inside the plugin repo — so the system travels with the code.

Load this file at the start of a Claude Code session on a new plugin repo to recreate the system.

---

## What This System Is

Every component directory in the plugin gets four doc files:

| File | Purpose |
|------|---------|
| `README.md` | What this component does, its key patterns, what it connects to |
| `RUNBOOK.md` | How-tos, procedures, recurring tasks specific to this component |
| `TROUBLESHOOTING.md` | Known issues — symptom, root cause, fix |
| `CHANGELOG.md` | Change history for this component |

The plugin root gets the same set at the plugin level. Every README links to its sibling
docs and to related components.

The result: any Claude session can read a component directory, understand what it does,
how it connects to the rest of the plugin, and find known issues — without asking.
The docs also build shared context for team members joining the project.

---

## How This Differs from the Server Version

| Aspect | Server version | Plugin version |
|--------|---------------|----------------|
| Unit of organisation | Service (container/process) | Component (plugin subdirectory) |
| Settings location | `~/.claude/settings.json` (personal, per machine) | `.claude/settings.json` in plugin root (committed, shared) |
| Hook git push | Yes — docs push immediately | No — docs commit locally, push follows normal PR workflow |
| Path filter in hook | Hardcoded absolute path | Portable — detects git root from the edited file |
| Python command | `python3` (Linux) | `python` (Windows-compatible; swap for `python3` on Linux/Mac) |
| Basename extraction | `bash basename` | `os.path.basename()` via Python — required on Windows where backslashes break bash `basename` |
| CLAUDE.md location | Repo root (server repo) | Plugin root |

---

## Step 1 — Component Structure

Identify the plugin's component directories. Typical WordPress plugin layout:

```
plugin-root/
  includes/
    admin/
    conditional-logic/
    dynamic-tags/
    extensions/
    modules/
    templates/
    utils/
    widget-extensions/
    widgets/
  README.md
  RUNBOOK.md
  TROUBLESHOOTING.md
  CHANGELOG.md
```

For each component directory, create four doc files. Minimum viable content:

**README.md** — must include what the component does, its key patterns, and:
```markdown
## Connects to

- [../other-component](../other-component/README.md) — what for
```

And a docs nav table:
```markdown
## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
```

**RUNBOOK.md** — one `##` section per procedure. If nothing yet:
```markdown
# Component Name — Runbook

Procedures and how-tos for working in this section.

---

*Add procedures as they come up.*
```

**TROUBLESHOOTING.md** — one `##` section per issue. If nothing yet:
```markdown
# Component Name — Troubleshooting

Known issues, their causes, and fixes.

---

*Add issues as they are encountered.*
```

**CHANGELOG.md** — reverse-chronological entries. If nothing yet:
```markdown
# Component Name — Changelog

---

*Add entries as changes are made.*
```

The root-level four files cover the plugin as a whole. The root README should have a
table linking to all component READMEs.

---

## Step 2 — CLAUDE.md

Create a `CLAUDE.md` at the plugin root. Sections to include:

```markdown
## Plugin structure

One directory per component under `includes/`. Each contains:
- `README.md` — what it does, key patterns, what it connects to
- `RUNBOOK.md` — how-tos and procedures
- `TROUBLESHOOTING.md` — known issues and fixes
- `CHANGELOG.md` — change history

Root-level docs cover the plugin as a whole.

## Knowledge filing rules

**Before starting work on a component:**
- Read its README, RUNBOOK, and TROUBLESHOOTING before making changes.
- Note what is empty or stale.

**During work:**
- Any time a pattern is explained, a workaround is found, or a non-obvious decision is made:
  check if it is already documented. If not, write it to the appropriate file immediately.
- Update stale content when spotted — if a README describes a pattern that has since changed,
  correct it now, not later.

**Before ending a session:**
- Look back at what was covered: bugs fixed, patterns explained, procedures run, changes made.
- Verify each one is filed. If knowledge was produced verbally, write it down before closing.
- Don't close a session with unfiled knowledge.

**Filing guide:**
- Bug found and fixed → `TROUBLESHOOTING.md` (symptom, root cause, fix)
- Procedure or command explained → `RUNBOOK.md`
- Code change made to a component → `CHANGELOG.md` for that component
- Cross-plugin change → root `CHANGELOG.md`
- Do not wait to be asked.
```

Add plugin-specific context below: what the plugin does, key third-party dependencies
(Elementor, LifterLMS, etc.), architectural patterns, development workflow conventions.

---

## Step 3 — .claude/settings.json (in the plugin repo)

Create `.claude/settings.json` at the plugin root. This file is committed to the repo
so every team member gets the same doc permissions and auto-commit hook.

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Write|Edit",
        "hooks": [
          {
            "type": "command",
            "timeout": 30,
            "statusMessage": "Filing to git...",
            "command": "FILEINFO=$(python -c \"import sys,json,os; d=json.load(sys.stdin); f=d.get('tool_input',{}).get('file_path',''); print(f+'|'+os.path.basename(f))\"); FILE=$(echo \"$FILEINFO\" | cut -d'|' -f1); FNAME=$(echo \"$FILEINFO\" | cut -d'|' -f2); [ -n \"$FILE\" ] || exit 0; echo \"$FNAME\" | grep -qiE '^(README|RUNBOOK|TROUBLESHOOTING|CHANGELOG)\\.md$' || exit 0; FDIR=$(python -c \"import os,sys; print(os.path.dirname(os.path.abspath(sys.argv[1])))\" \"$FILE\") && REPO=$(git -C \"$FDIR\" rev-parse --show-toplevel 2>/dev/null) && [ -n \"$REPO\" ] || exit 0; cd \"$REPO\" && git add -A && git diff --cached --quiet && exit 0; git commit -m \"docs: update $FNAME\""
          }
        ]
      }
    ]
  }
}
```

**What this does:**
- After every Write or Edit to a `README.md`, `RUNBOOK.md`, `TROUBLESHOOTING.md`, or
  `CHANGELOG.md` anywhere in the plugin, automatically stages and commits it.
- Detects the git repo root from the edited file's location — no hardcoded paths.
- Commits only, no push. Pushes follow your normal PR workflow.
- `CLAUDE.md` and code files are intentionally excluded — those should always require approval.

**Notes:**
- Uses `python` (not `python3`) for Windows compatibility. On Linux/Mac, swap to `python3`
  if `python` is not available.
- Uses `os.path.basename()` instead of bash `basename` — required on Windows because
  backslashes in Windows paths cause bash `basename` to return incorrect results.
- Because the hook is in the plugin repo's `.claude/settings.json`, it only fires when
  Claude Code is opened with the plugin directory as the project root.

**Permissions:**
Doc file permissions (allowing Claude to write/edit without prompting) are intentionally
left out of this shared file because paths vary per developer. Each developer adds their
own doc file permissions to their personal `~/.claude/settings.json`:

```json
{
  "permissions": {
    "allow": [
      "Read",
      "Write(/path/to/plugin/README.md)",
      "Write(/path/to/plugin/RUNBOOK.md)",
      "Write(/path/to/plugin/TROUBLESHOOTING.md)",
      "Write(/path/to/plugin/CHANGELOG.md)",
      "Write(/path/to/plugin/includes/*/README.md)",
      "Write(/path/to/plugin/includes/*/RUNBOOK.md)",
      "Write(/path/to/plugin/includes/*/TROUBLESHOOTING.md)",
      "Write(/path/to/plugin/includes/*/CHANGELOG.md)",
      "Edit(/path/to/plugin/README.md)",
      "Edit(/path/to/plugin/RUNBOOK.md)",
      "Edit(/path/to/plugin/TROUBLESHOOTING.md)",
      "Edit(/path/to/plugin/CHANGELOG.md)",
      "Edit(/path/to/plugin/includes/*/README.md)",
      "Edit(/path/to/plugin/includes/*/RUNBOOK.md)",
      "Edit(/path/to/plugin/includes/*/TROUBLESHOOTING.md)",
      "Edit(/path/to/plugin/includes/*/CHANGELOG.md)",
      "Bash(git add:*)",
      "Bash(git commit:*)"
    ]
  }
}
```

Replace `/path/to/plugin` with the actual absolute path on your machine.
Add deeper wildcard levels (`includes/*/*`) if your plugin has nested component directories.

**Prerequisites:**
- `git` configured in the plugin directory
- `python` (or `python3`) available in your shell
- Plugin directory is a git repo with at least one commit

---

## Step 4 — Root README component table

The root `README.md` should have a table linking to every component README, so any
session can navigate the whole plugin from one file:

```markdown
## Components

| Component | What it does |
|-----------|-------------|
| [admin](includes/admin/README.md) | ... |
| [widgets](includes/widgets/README.md) | ... |
| [templates](includes/templates/README.md) | ... |
| [utils](includes/utils/README.md) | ... |
```

---

## Step 5 — Monthly compile pass

Same as the server version. Schedule a recurring reminder: first of each month.

When it fires:

> "Do a compile pass on the [plugin name] docs — read all the component docs, identify gaps and stale content, fill what you can."

---

## What to customise per plugin

- **Component list** — the `includes/` subdirectories depend on the plugin. Adjust the
  directory structure in Step 1 to match.
- **CLAUDE.md plugin context** — add: what the plugin does, key dependencies, architectural
  patterns (e.g. Elementor Widget_Base vs Widget_Button inheritance), workflow conventions.
- **`python` vs `python3`** — swap in the hook command to match what is available on your system.
- **Deeper nesting** — if components have sub-components (e.g. `widgets/courses/`), add
  a `README.md` at the sub-component level and extend the wildcard patterns in permissions.
- **Developer permissions** — each developer configures their own `~/.claude/settings.json`
  with the paths matching their local clone location.

---

## Checklist

- [ ] Four doc files exist for every component directory
- [ ] Every component README has a Docs nav table and a Connects to section
- [ ] Root README has a component table linking to all component READMEs
- [ ] CLAUDE.md exists at plugin root with knowledge filing rules and plugin context
- [ ] `.claude/settings.json` exists at plugin root with auto-commit hook (committed to repo)
- [ ] Each developer has added doc file permissions to their personal `~/.claude/settings.json`
- [ ] Git repo initialised with at least one commit
- [ ] Monthly compile pass scheduled
