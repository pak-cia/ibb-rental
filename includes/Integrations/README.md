# Integrations

Home for all third-party integrations and compatibility shims. Each integration is a **self-contained module** in its own subdirectory; the plugin works fully without any of them.

| Module | Provides |
|---|---|
| [Elementor](Elementor/README.md) | Dynamic-tag for the Pro Gallery widget — pick property + (optional) sub-gallery slug, get `[{id, url}]` array of attachments |

Future modules (planned, not implemented yet): WPML / Polylang multi-language, Bricks Builder dynamic data, Beaver Builder modules, smart-lock platforms (August / Yale).

## Module convention

Every integration follows the same shape, so adding a new one is cookie-cuttable.

```
includes/Integrations/<Provider>/
    Module.php                                 # entry point — implements register(): void
    <Subsystem>/                               # one subdir per kind of leaf class
      <Whatever>.php
    README.md / RUNBOOK.md / TROUBLESHOOTING.md / CHANGELOG.md   # module-specific docs
```

- `Module.php` is the **only** class instantiated by `IBB\Rentals\Plugin::boot()`. It's loaded unconditionally; its `register()` method gates on the provider being available (via `class_exists` or the provider's "loaded" action) so it's a no-op when the provider isn't installed.
- Subdirectories (`DynamicTags/`, `Widgets/`, `Controls/`, `Hooks/`, …) hold leaf classes. The `Module` `require_once`s them lazily — never via PSR-4 autoload — because they typically extend the provider's base classes which don't exist until *its* autoloader has run.
- Each module gets its own four-doc set so module-specific patterns / known issues / changelogs stay scoped.

## Key patterns

- **Conditional loading** — every integration checks `class_exists('<ProviderBaseClass>')` at the top of its leaf-class files (which `return` early if absent) and gates `register()` on the provider's "loaded" action where one exists. Saves a fatal when the provider is deactivated.
- **Lazy class declaration** — provider base classes don't exist until the provider's autoloader has loaded. Don't rely on PSR-4 to resolve leaf classes; `require_once` them explicitly inside the registration callback that fires on the provider's loaded action.
- **Stable provider APIs only** — most providers have experimental/internal APIs that change between releases. Stick to documented public APIs.
- **No coupling back into the provider** — integrations produce data and register hooks; the provider's widgets / runtime do the rendering. We don't ship custom widget templates that have to keep up with provider design changes.

## Connects to

- [../Domain](../Domain/README.md) — most integrations consume `Property::from_id` + accessors
- [../PostTypes](../PostTypes/README.md) — for property listings / pickers
- [../Plugin.php](../Plugin.php) — boot calls `(new <Provider>\Module())->register()` once per integration; the inner gating decides whether anything happens

## Adding a new integration

For any new third-party (Bricks, Beaver Builder, Divi, WP Fusion, WPML, …):

1. Create `Integrations/<Provider>/Module.php` with a class `Module` in namespace `IBB\Rentals\Integrations\<Provider>`. It exposes `register(): void`.
2. Inside `register()`, gate on the provider being available — usually by hooking the provider's "loaded" action and/or `class_exists` checks.
3. Place leaf classes (widgets, dynamic tags, hooks, controls) under subdirectories of `Integrations/<Provider>/`. Match the provider's own conceptual divisions (`DynamicTags/`, `Widgets/`, etc.).
4. `require_once` leaf-class files inside callbacks fired by the provider's actions, never via PSR-4 autoload.
5. Wire it from `Plugin::boot` with a single `( new <Provider>\Module() )->register()` line.
6. Add the module's four-doc set under `Integrations/<Provider>/`.
7. Add a row to the table at the top of this README.

## Docs

| | |
|--|--|
| [RUNBOOK.md](RUNBOOK.md) | How-tos and procedures |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Known issues and fixes |
| [CHANGELOG.md](CHANGELOG.md) | Change history |
