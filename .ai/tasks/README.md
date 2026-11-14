# Úkoly – moneta-laravel-package

Jeden úkol = jeden soubor `.ai/tasks/NNN-nazev.md` = jeden commit. Části: Cíl, Kontext, Kroky, Nedělat, Hotovo když, Ruční kroky pro Petra, Po dokončení.

V Cursoru: *„Udělej úkol @.ai/tasks/NNN-….md“*. Dělej jen to, co je v zadání; další nálezy napiš do shrnutí. Necommituj.

Stavy: `todo` · `doing` · `done` · `blocked`

| # | Úkol | Stav |
|---|------|------|
| 001 | Specifikace, CI, CHANGELOG a nástroje jako u comgate | done |
| 002 | Částky v haléřích a úplná transakce | done |
| 003 | Profily a automatický sandbox token, výjimka NARR | done |
| 004 | Lazy procházení transakcí a introspect tokenu | done |
| 005 | `Moneta::fake()` s builderem a asercemi | done |
| 006 | Vydání 1.2.0 | done |
| 007 | PISP: dávkové platby k podpisu, vydání 1.3.0 | todo |
| 008 | Opravy po kontrole 1.2.2 (fake a profily, znaménko zůstatku, 429, neplatná částka, chybějící reference) | todo |
| 009 | Artisan příkazy `moneta:accounts` a `moneta:check` | todo |
| 010 | Výpis v JSON a kontrolní součty | todo |
| 011 | Vydání 1.4.0 | todo |

Pořadí: 001 → … → 006 → 007 (1.3.0) → 008 → 009 → 010 → 011 (1.4.0). Aplikace it-elektro (úkol 008) staví na 1.4.0. Pozor: tag `1.2.0` ukazuje na kód 1.1.0, platná 1.2.x je `1.2.2`.
