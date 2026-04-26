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

## Preconditions

Before starting, confirm:

- **Git** is installed and `user.name` / `user.email` are configured globally. The hook commits as the current user and won't work without this.
- **Python** is on PATH (`python --version` should return without launching the Microsoft Store stub on Windows). The hook uses Python for path manipulation. Test with `python -c "import sys, json, os; print('ok')"`.
- **The plugin is a git repo with at least one commit.** The hook calls `git rev-parse --show-toplevel` and bails silently if the file isn't inside a repo. If you're starting from a fresh `git init`, make at least one commit (even an empty `.gitkeep`) before triggering the hook.
- **The plugin is activatable** in your dev WordPress install. Setting up docs for a plugin that fatals on activation just hides the underlying problem — fix that first.

If any of these fail, fix them before proceeding. The hook fails silently when something's wrong, which makes a misconfigured setup look like a working one.

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

**Naming convention is whatever your plugin already uses.** PSR-4 PascalCase (`Admin/`, `Cron/`, `Repositories/`) is fine. Lowercase-hyphen (`admin/`, `widget-extensions/`) is fine. The doc system doesn't care — pick what matches the autoloader and stay consistent.

**Subdirectory rule of thumb.** When `includes/<Component>/` has a subdirectory like `Cron/Jobs/` or `Rest/Controllers/`: use **one** doc set at the parent level unless the subdirectory is a genuinely separate concern. Thin sub-folders that just hold leaf files (e.g. all the AS job classes inside `Cron/Jobs/`) are part of the parent component, not their own. Only nest a second doc set when a subdirectory has its own pattern, its own connections, and its own troubleshooting story.

For each component directory, create four doc files. Minimum viable content:

**README.md** — must include what the component does, its key patterns, and what it connects to. The recommended subsection set:

```markdown
# Component Name

[1-2 sentence purpose]

## Files

- `Foo.php` — what it does
- `Bar.php` — what it does

## Key patterns

[bullet list of architectural notes, conventions, "why we did it this way"]

## Connects to

- [../other-component](../other-component/README.md) — what for

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
```

The "Files" subsection is the single biggest difference from a generic README. It pulls each file's docblock summary into the component-level entry point so a Claude session reading just the README knows what every file does without opening them. Match each entry to the file's actual top-of-file `/** */` docblock; if there isn't one, write one and copy it.

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

**Add `.claude/settings.local.json` to `.gitignore`:**

The shared `.claude/settings.json` IS committed (so the auto-commit hook is consistent across the team). Per-developer overrides go in `.claude/settings.local.json` at the same path — this file should be gitignored:

```
# Per-developer Claude overrides (the shared .claude/settings.json IS committed).
.claude/settings.local.json
```

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

## Step 4 — Root README structure

The root `README.md` is the developer / contributor / Claude entry point. It should make it possible to orient on the whole plugin in 60 seconds without opening any other file.

The Components table is the navigation, but the root README needs more than that. The minimum recommended structure:

```markdown
# Plugin Name

[1-2 sentence what-it-does, plus a note distinguishing this README.md from any
WordPress.org-style readme.txt that exists in the repo.]

## Status

What's shipped, what's still pending verification, what's deferred. This is
how a returning contributor knows where they left off.

## What this plugin does

User-facing feature bullets.

## Architecture at a glance

A file-tree showing the top-level layout. Plus a "Key conventions" bullet
list — naming, namespace, hooks, table prefixes, postmeta prefixes.

## Components

| Component | What it does |
|-----------|--------------|
| [Foo](includes/Foo/README.md) | ... |

## Boundary choices

The WHY behind the architectural decisions. One row per decision,
with the trade-off accepted. (Full ADR lives in `docs/architecture.md`.)

## Roadmap

v1.0 (shipped), v1.1 (deferred), v1.2+ (future). Items in priority order
within each tier. Cross-link to the ADR for rationale.

## Risks & known limitations

Short table: risk → mitigation. Full register in the ADR.

## External libraries

Vendored deps + their purpose. Note any optional ones that the plugin
gracefully degrades without.

## Public hooks

Short reference — list of hook constants with link to the constants file.
Full table with args and timing in the ADR.

## Testing

Pointer to the manual smoke-test in RUNBOOK.md, plus what's automated /
deferred.

## Installation (for development)

How to clone and get running locally. End-user install lives in readme.txt.

## Docs

| | |
|--|--|
| [CLAUDE.md](CLAUDE.md) | ... |
| [RUNBOOK.md](RUNBOOK.md) | ... |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | ... |
| [CHANGELOG.md](CHANGELOG.md) | ... |
| [docs/architecture.md](docs/architecture.md) | ... |
```

**The principle**: high-level planning info lives at the root in short, scannable form. Detail lives in component docs or in the ADR. Don't duplicate — *reference*. A reader scanning the root README in 60 seconds should know what's in the plugin, what state it's in, what was deferred, what risks to be aware of, and where to go for detail.

---

## Step 5 — Monthly compile pass

Same as the server version. Schedule a recurring reminder: first of each month.

When it fires:

> "Do a compile pass on the [plugin name] docs — read all the component docs, identify gaps and stale content, fill what you can."

---

## When retrofitting an existing plugin

The setup above assumes a greenfield plugin. Most plugins adopting this system are *not* greenfield — they already have code, a `readme.txt`, and probably a `CHANGELOG.md`. Three additional steps apply.

### Preserve pre-existing files instead of replacing them

| File you might find | What to do |
|---|---|
| `readme.txt` (WordPress.org plugin-directory format with `=== Plugin Name ===` headers) | **Keep as-is.** It's the public-facing readme for the WordPress.org directory; it has its own format, audience, and update lifecycle. Add a note at the top of the new `README.md` clarifying that `README.md` is the developer entry point and `readme.txt` is the public one. |
| Existing `README.md` written for developers | Read it carefully. Meld the content into the new structured README without dropping anything — most likely it becomes the "What this plugin does" + "Installation" sections. |
| Existing `CHANGELOG.md` | **Keep its entries verbatim.** Add an `[Unreleased]` section above the existing entries for new work. Add the docs nav block at the bottom. Do not rewrite history. |
| File-level docblocks on PHP files | Distil into each component README's "Files" subsection. The docblock author often captured constraints / rationale that would otherwise be lost when a future maintainer skims. |
| `composer.json` description, plugin header description | Reuse verbatim in the root README intro. They're already concise summaries the author thought hard about. |

### Preserve planning artifacts as an Architecture Decision Record

If a planning document exists for the plugin (a design doc, an architecture proposal, the original spec) — **commit it to the repo at `docs/architecture.md`** rather than letting it live in chat history or a personal Notes app.

The ADR captures *why* the system is shaped the way it is. Component READMEs describe the as-built state; the ADR describes the rationale that's not obvious from looking at the code. Future maintainers reading "wait, why didn't we just use postmeta?" need this file.

Recommended ADR structure:

```markdown
# Architecture decision record — Plugin Name v0.1.0

> **Looking for current docs?** Component-level READMEs describe the system
> as it stands today. This file is historical context — *why* the system
> is shaped the way it is.

## Context (as of YYYY-MM-DD)

[The original problem statement, copied from the plan.]

## Architecture summary

### Boundary choices

| Choice | Rationale | Trade-off accepted |
|---|---|---|

### Phasing

v1.0 (shipped) / v1.1 (deferred) / v1.2+ (future).

## Data model

[High-level — point at Schema.php for the DDL.]

## Risks & known limitations

[Full register; root README has the short table.]

## External libraries

## Testing strategy

[Manual now / automated planned, with critical scenarios listed.]

## Public hooks contract

| Constant | Hook name | Args | When |
|---|---|---|---|

## Critical files

The 8-12 files that anchor the system, in read-order.

---

*This file is reverse-chronological — append future architectural changes below.*

## ADR additions since v0.1.0

*(none yet)*
```

The "ADR additions since v0.1.0" section is where future architectural decisions go — append-only. Don't rewrite the v0.1.0 plan when the architecture evolves; add a new dated subsection capturing the change and why.

### Seed TROUBLESHOOTING.md from build history

When retrofitting, you have months or years of bug-fix history that's already documented somewhere — git commit messages, closed issues, Slack threads, file comments saying *"this is here because…"*. Mine that history before declaring the doc system complete.

Walk through:

1. **Recent commit messages** (especially anything with "fix", "workaround", "hotfix" in the message). Each one represents a problem someone hit. Categorise by component and write a TROUBLESHOOTING entry with **Symptom**, **Root cause**, **Fix** (with file links).
2. **Closed issues / PRs** in the issue tracker, especially anything reopened multiple times.
3. **Inline `// HACK:` / `// FIXME:` / "this is weird because…"** comments in the code. These are bugs that someone found a workaround for; the workaround belongs in TROUBLESHOOTING.
4. **Decisions visible in commit history that aren't visible from the code** — e.g. *"why doesn't this just use the obvious approach?"* The reason is somewhere in history; surface it.

You don't need to be exhaustive. Aim for the 5–10 most likely "you'll hit this" issues per component. New issues get added as they come up; this is the seeding pass.

If the plugin is brand-new (no history yet), skip this — fresh issues will accumulate organically.

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

**Preconditions**
- [ ] Git installed with `user.name` / `user.email` configured globally
- [ ] `python` (or `python3`) on PATH and runnable
- [ ] Plugin is in a git repo with at least one commit
- [ ] Plugin is activatable in a dev WP install (no fatals)

**Pre-existing files (skip if greenfield)**
- [ ] `readme.txt` (WP.org format) preserved as-is; root `README.md` notes the distinction
- [ ] Existing `CHANGELOG.md` entries kept verbatim; `[Unreleased]` section added above
- [ ] File-level docblocks distilled into each component README's Files subsection

**Doc structure**
- [ ] Four doc files exist for every component directory (PSR-4 PascalCase or whatever the autoloader uses)
- [ ] Every component README has Files / Key patterns / Connects to / Docs nav subsections
- [ ] Subdirectories that aren't separate concerns share their parent's doc set (no over-nesting)
- [ ] Root README includes Status, Boundary choices, Roadmap, Risks, External libraries, Public hooks, Testing, and Components table — not just the Components table
- [ ] `CLAUDE.md` exists at plugin root with knowledge filing rules + plugin-specific context (naming conventions, architectural patterns, "what lives where" cheatsheet)

**Architecture decision record**
- [ ] Original planning artifact preserved at `docs/architecture.md` with boundary choices, full risk register, full hooks contract, testing strategy
- [ ] ADR has an "ADR additions since v0.1.0" reverse-chronological section ready for future architectural changes

**TROUBLESHOOTING seeding (skip if greenfield)**
- [ ] Recent commit history mined for fix-it-now opportunities; entries filed under the relevant component
- [ ] `// HACK:` / `// FIXME:` / "this is weird because…" comments surfaced into TROUBLESHOOTING

**Hook setup**
- [ ] `.claude/settings.json` committed at plugin root with auto-commit hook
- [ ] `.claude/settings.local.json` added to `.gitignore`
- [ ] Each developer has added doc file permissions to their personal `~/.claude/settings.json`
- [ ] Hook smoke-tested by editing a doc file and confirming it auto-commits

**Ongoing**
- [ ] Monthly compile pass scheduled
- [ ] If the system is being copied INTO the implementing repo (so future contributors can replicate it elsewhere), add a status banner at the top of the copy noting the repo already follows the system
