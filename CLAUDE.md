## PHP Coding Style

Follow PSR-12 (https://www.php-fig.org/psr/psr-12/) coding standards with the following modifications / additions:

- Tabs instead of spaces
- There should be a blank line before and after control structures (unless they are the first or last line within a function or file)
- No single line if statements (or other control structures such as switch statements)
- A blank line should appear before a return statement if a non-control structure line precedes it
- Case statements should not have a return on the same line as the case

## Database Schema Changes

Any DDL change — a new table, a new or altered column, an index, a width, a charset — is
three edits in one commit, never one:

1. **A new revision file** `core/admin/ajax/developer/upgrade/revisions/N.php`, so an existing
   install gets there. Follow `_template.php` and the conventions in the same directory's
   `_README.md` (`MigrationService::begin(N)` / `finish(N)`, every step guarded so the file is
   idempotent and a batched run is resumable).
2. **`core/setup/base.sql`**, so a fresh install is born in the new state. base.sql is the END
   STATE, not a historical starting point — a fresh install is installed up to date, never
   installed and then upgraded. Bump the `bigtree-internal-revision` floor it seeds in the same
   edit.
3. **`BIGTREE_REVISION` in `core/version.php`**, which must equal that floor.

Skipping step 2 is the failure that hides longest: nothing errors, and every fresh install
quietly relies on migrations to finish building its own schema. `MigrationRunnerTest` asserts
the floor matches `BIGTREE_REVISION`, which catches a missed step 3 but cannot see a missed
step 2.

The only table allowed to be absent from base.sql is `bigtree_ai_embeddings` — its `VECTOR`
column needs MySQL 9+ / MariaDB 11.7+ and base.sql has to import on every supported server, so
`core/setup/install.php` creates it conditionally. Anything else missing from base.sql is a bug.

Where a table's DDL is also declared in PHP for self-healing (`AIChatService::ensureTables()`,
`ProposalStore::ensureTables()`, `EmbeddingService::ensureTable()`), that copy has to match
base.sql too.

New tables and columns are `utf8mb4` / `utf8mb4_general_ci` — declared explicitly, never left
to the server default, and never `utf8mb4_0900_ai_ci` (MySQL 8+ only, so it breaks MariaDB, and
mixing collations throws "Illegal mix of collations" on a join). An indexed `varchar` is at
most **191** characters: a full-column index on `varchar(1024)` is 4096 bytes at utf8mb4, over
InnoDB's 3072-byte key prefix limit. Index a wider column with a `(191)` prefix instead.

## React Coding Style

- Components should use interfaces for their properties rather than being defined inline.
- Components should be defined as const rather than functions.
- Components that are potentially reusable or more than a 10 lines of code should be their own file.
- Prettier formatting should be applied to all files.
- Tabs instead of spaces
- There should be a blank line before and after control structures (unless they are the first or last line within a function or file)
- No single line if statements
- A blank line should appear before a return statement if a non-control structure line precedes it — if the return statement is the only line in a function (or a case statement), it does not need an empty line before it
