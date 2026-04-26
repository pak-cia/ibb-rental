# Integrations — Runbook

Module-specific procedures live in each module's own RUNBOOK (e.g. [Elementor/RUNBOOK.md](Elementor/RUNBOOK.md)). Keep cross-cutting/parent-level procedures here.

## Add a new integration

See "Adding a new integration" in [README.md](README.md#adding-a-new-integration). The short version:

1. `mkdir Integrations/<Provider>/` plus `mkdir` any subsystem subdirectories the integration needs (`DynamicTags/`, `Widgets/`, etc.).
2. Create `Integrations/<Provider>/Module.php` with `register(): void` gated on the provider's loaded action.
3. Add `( new \IBB\Rentals\Integrations\<Provider>\Module() )->register();` in `Plugin::boot()`.
4. Create the four-doc set under `Integrations/<Provider>/`.
5. Add a row to the modules table in this directory's [README.md](README.md).

## Disable an integration without uninstalling its provider

Comment out the `( new <Provider>\Module() )->register()` line in `Plugin::boot()`. Or filter the provider's "loaded" action to bail before our registration callback runs. The module's gating means the rest of the plugin keeps working.

## Quick smoke-test after restructuring or upgrading an integration

1. Load any wp-admin page on the site — should be no fatal at boot regardless of whether the provider is active.
2. With the provider deactivated, browse the front-end — every IBB feature should still work; the integration just doesn't surface its widgets/tags/etc.
3. Re-activate the provider — its editor / control panel should show the IBB Rentals group / widget / etc. without errors.
