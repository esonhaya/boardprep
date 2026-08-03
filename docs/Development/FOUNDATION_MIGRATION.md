# Foundation Migration

This document tracks the migration from native PHP helpers to the Foundation layer.

---

## Html

Status: 🟡 In Progress

Replace:

- htmlspecialchars()

Do NOT replace:

- Core/
- ExceptionHandler.php

---

## Arr

Status: 🟡 Planned

Replace:

- Repeated array access in Services

Do NOT replace:

- $_GET
- $_POST
- $_SERVER
- $_ENV

---

## Collection

Status: ⬜ Planned

Replace:

- count(array_filter())
- array_map()
- array_filter()
- array_values()

---

## Str

Status: ⬜ Planned

Replace repeated string helpers only when duplication exists.

---

## Rules

1. Verify before migrating.
2. Migrate by feature, not by search-and-replace.
3. Keep commits small.
4. Never break Core.
5. Foundation must remain framework-agnostic.
