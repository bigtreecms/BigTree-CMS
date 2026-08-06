# Writing AI tools in an extension

The assistant's tool catalogue is open: an extension can add a capability the core
tools don't cover, and it runs through exactly the same registry, permission gate,
argument validator and approval flow the built-in tools do. What it cannot do is
bypass any of them.

This document exists because the contract was, until audit #18, documented only in
the docblocks of `ExtensionTools` and `Tools\ApprovableTool` — so nothing told an
extension author that (for example) returning a `fingerprint` descriptor is what
buys their tool staleness protection. Everything here is a restatement of what the
framework already does; the code is the authority, and the classes named below are
worth reading alongside it.

---

## 1. Where a tool lives

`ExtensionTools::providerFiles()` globs two locations:

```
extensions/{id}/api/ai-tools/*.php
custom/inc/bigtree/api/ai-tools/*.php
```

A provider file **returns** one `AIToolInterface` instance or a list of them, and
has a ready `$proposal_store` (a `ProposalStore`) in scope for two-phase tools:

```php
<?php
	use BigTree\Services\AI\Tools\AbstractReadTool;

	return [
		new MyExtensionReportTool(),
		new MyExtensionArchiveTool($proposal_store),
	];
```

Rules the loader enforces, so you don't have to:

- **Core names win.** A tool whose `name()` matches a built-in is dropped, not
  registered — an extension cannot shadow `update_page`.
- **A bad provider file is skipped, not fatal.** A file that throws is logged and
  the turn continues without it.
- Anything returned that isn't an `AIToolInterface` is ignored.

## 2. The interface

`AIToolInterface` has five methods, and permission is expressed in three of them:

| method | contract |
| --- | --- |
| `name()` | stable snake_case name the model sees |
| `kind()` | `"read"`, `"mutate"` or `"elicit"` — **enforced**: a `read` tool that stages a proposal is refused at dispatch |
| `isAvailable($user)` | registry gate; a tool a user can't use is never offered, so the model can't hallucinate the capability |
| `definition($user)` | OpenAI-shaped function schema; may vary per user |
| `execute($args, $context)` | re-checks permission server-side and returns an `AIToolResult` |

`AIToolRegistry::execute()` validates `$args` against your own published schema
(`AIToolArgs`) *before* dispatch, for extension tools exactly as for core ones — so
a wrongly-shaped argument never reaches your code as a silently-emptied value.

Return one of the `AIToolResult` factories, never a bare array: `ok()`, `denied()`
(with `alternatives`), `needsInput()` (question + options, rendered as choice chips),
`error()` (recoverable — the model may fix and retry), `proposal()`,
`needsPriorChange()`.

## 3. Read tools

Extend `Tools\AbstractReadTool`, keep the payload small, and obey the two rules
every core read seam obeys:

- **Filter by permission before you cap**, never after. A cap applied first turns
  "there is no page called X" into an answer derived from a truncated scan.
- **Say when a result is partial.** Rows: `has_more` (plus the count actually
  returned, so the model pages by what it got, not by what it asked for). Values:
  list the fields you cut (`fields_truncated` / `content_truncated`) — a value the
  model only saw in part is a value it must not write back.

Sizing is a contract, not a preference. `PayloadBudget::RESULT_CHARS` bounds one
tool result and `PayloadBudget::TURN_CHARS` bounds a whole turn's reading; a seam
whose worst case exceeds the former must bound itself with
`PayloadBudget::fitRows()` (drop rows) or `PayloadBudget::capForValues()` (read
values shorter, quantized to a ladder `TruncatedRead` knows).

`AgentLoop` enforces the per-result ceiling whether or not you do, but only as a
backstop: an oversized result is replaced *in full* by a refusal telling the model
to ask for less, so the read is wasted. Budget the seam and you keep the rows that
fit; don't, and you keep none of them.

## 4. Mutating tools: validate, stage, approve

**A mutating tool never changes the CMS during a turn.** It stages a proposal; the
user approves the card; the write runs later from the stored payload.

Extend `Tools\AbstractMutatingTool` and end `execute()` in
`stageFromValidation($validation, $context, $this->name())`. Your validation seam
returns one of:

```php
["denied"     => "…", "alternatives" => […]]   // permission
["error"      => "…"]                           // recoverable
["needs_input" => ["question" => "…", "options" => […]]]
["ok" => true, "summary" => "…", "preview" => […], "payload" => […]]
```

The `payload` is what the write will later run from — resolved ids and validated
values, never anything the model round-tripped.

Three optional descriptors on that array buy you the rest of the framework for
free. This is the part nothing outside the source said:

- **`fingerprint`** — a data-only descriptor of what the proposal is *about*
  (`["type" => "row", "table" => "my_table", "id" => 12, "columns" => [...]]`, and
  the other shapes in `ProposalFingerprint`). Hashed at staging, re-hashed at
  approval; a record that moved underneath the card refuses instead of writing.
  **Omit it and your proposal has no staleness protection at all** — cards live up
  to 24 hours.
- **`lock`** — a content-lock descriptor, so the card says who else is holding the
  record. Re-asked at approval rather than replayed from a day-old snapshot.
- **`prior_change`** — names the pending proposal this one depends on. Blocking
  yields `needs_prior_change`; non-blocking records the dependency so approving out
  of order refuses with the prerequisite named.

To be executable on approval, implement `Tools\ApprovableTool`:

```php
public function executeApproved(array $payload, $user): array;
```

`AIChatService::approveProposal` runs the prerequisite check, the staleness check
and the content-lock note **before** it branches to core or extension dispatch, so
an extension tool can't skip them. It then re-checks your coarse `isAvailable($user)`
gate — defense-in-depth for a revoked level, **not** a substitute for the
object-scoped permission re-check `executeApproved()` still owns.

Return `["mode" => "error", "message" => "…"]` to record a failed approval (the card
explains itself and stays retryable) rather than throwing for an expected refusal.
Name `audit_table` / `audit_entry` in the result if you want the audit row to point
at your own record; otherwise the approval audits against the proposal itself. Every
row is tagged `via=ai_assistant`.

## 5. What the framework will not do for you

- It will not make the model your enforcement layer. Re-check permission in
  `execute()` *and* in `executeApproved()`.
- It will not sanitize your payload into the CMS. Validate values in your seam, the
  way the core `ai*` service methods do.
- It will not read your mind about truncation: a value your read seam cut and your
  write seam accepts back is data loss, and `TruncatedRead::violation()` only knows
  the caps it has been told about.
