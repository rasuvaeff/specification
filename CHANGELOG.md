# Changelog

## 1.1.0 — 2026-07-25

- Ship an AI agent skill (`resources/skills/rasuvaeff-specification/SKILL.md`
  + `extra.skills` in composer.json): projects using the `llm/skills` Composer
  plugin get the skill synced into `.agents/skills/` automatically on install.
- Bump dev dependency `rasuvaeff/property-testing` to `^2.6`.

## 1.0.2 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

## 1.0.1 — 2026-06-26

- Migrate tests from PHPUnit to Testo (testo/testo + testo/bridge-infection + testo/bench).

## 1.0.0 — 2026-05-30

Initial release.
