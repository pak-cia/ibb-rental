# Integrations — Runbook

## Use the Elementor dynamic tag

In Elementor's editor:
1. Add a Gallery widget (free version supports basic; Pro Gallery supports masonry/justified).
2. Click the Images control's dynamic-tag (database) icon.
3. Pick **IBB Rentals → Property Gallery**.
4. Set:
   - **Property** → "Current page" (default; auto-resolves from `get_the_ID()` on a property's single template) or a specific property by name.
   - **Gallery slug** → leave empty for all photos, or enter a sub-gallery slug like `bedroom-1`, `pool`, `kitchen`.
5. Save the layout.

The tag re-evaluates every render — switching properties (e.g. on a single-property template viewing different posts) automatically picks up the right images.

## Add another integration (Beaver Builder, Bricks, etc.)

See "Adding a new integration" in this component's [README.md](README.md).

## Force-refresh the property list inside Elementor's panel

The `ElementorIntegration::property_options()` cache is per-request. Reload the editor page (or save and refresh) to repopulate after adding a new property.
