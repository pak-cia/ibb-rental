# Integrations — Troubleshooting

Module-specific issues live in each module's own TROUBLESHOOTING (e.g. [Elementor/TROUBLESHOOTING.md](Elementor/TROUBLESHOOTING.md)). Keep cross-cutting / parent-level issues here.

## Module fatal-errors at plugin boot, even though the provider is inactive

**Symptom:** activating the IBB Rentals plugin fatals with `Class \<Provider>\<...> not found` despite the provider plugin being deactivated.

**Root cause:** somewhere inside the module, code references a provider's class at PSR-4 autoload time (e.g. an `extends \Provider\Foo` declaration in a class that's autoloaded at boot). PHP resolves the parent class as soon as the class is parsed; if the provider's autoloader hasn't loaded yet, that's a fatal.

**Fix:** every leaf class file in an integration module must:

1. Have a `class_exists('\<Provider>\<BaseClass>')` guard at the very top of the file that `return`s early before declaring the leaf class.
2. Be `require_once`d ONLY inside a callback fired by the provider's "loaded" action — never via PSR-4 autoload triggered by a `new` or `use` reference at boot time.

The module's `Module.php` (which IS PSR-4-autoloaded at boot) must NOT reference the provider's classes in its declaration; only in callbacks that fire after `<provider>/loaded`.

## Two integration modules conflict (e.g. dynamic-tag group name collision)

**Symptom:** registering a tag/widget/control fails with a "duplicate" warning from the provider.

**Likely cause:** another plugin registered our group/tag name first. Our group identifier is `ibb-rentals` (slug-style), our tag/widget names are prefixed `ibb-`. If a third party uses the same slug, you'll see this.

**Fix:** prefix collisions are extremely rare with our slug, but if it happens, filter the registered name in `Module::register_*()` to add an extra disambiguating suffix.

## Module not loading despite the provider being active

**Likely causes:**

1. The provider fires a *non-standard* "loaded" action (some forks of providers rename the action). Check the module's `register()` for which action it hooks; compare to the provider's documented action.
2. The plugin's `Plugin::boot()` doesn't instantiate the module's `Module` class. Verify the `( new <Provider>\Module() )->register();` line is present.
3. PSR-4 autoload mismatch — the file's namespace declaration doesn't match its path on disk. Check that `Integrations/<Provider>/Module.php` declares `namespace IBB\Rentals\Integrations\<Provider>;` exactly.
