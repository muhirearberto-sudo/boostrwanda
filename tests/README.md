# Tests

## Automated checks (`run_checks.php`)
Command-line script that exercises the money-handling and authorisation code against your real MySQL database:
registration rules, task approval -> wallet credit, double-payment prevention, withdrawal hold/refund,
sandbox payment confirmation/replay/failure, the non-negative-balance guard, conversation access and report scoping.

    C:\xampp\php\php.exe tests\run_checks.php

It leaves one test user and a few transactions behind (financial rows are never deleted). Re-import
`database/boostrwanda_db.sql` to return to a clean state.

> The author of this package could not run PHP in the build environment, so this script has been
> written but **not executed**. If any line shows `[FAIL]`, please report it with the line text.

## Manual test checklist
See "Testing the main workflows" in the top-level `README.md`.
