<?php

/**
 * Style gate for the actively-developed API + services tree.
 *
 * NOTE ON SCOPE: CLAUDE.md nominally says "PSR-12 with tabs," but the actual
 * BigTree house style is NOT PSR-12 — the whole file is indented one extra
 * level, braces are K&R (same line), and class constants are declared without
 * an explicit visibility keyword. Enabling "@PSR12" reflows all 97 files, and
 * the brace/blank-line fixers that would encode the CLAUDE.md rules disagree
 * with the code's existing nuances (e.g. blank-line-before-return is only wanted
 * after a non-control line, which no built-in fixer expresses). So this config
 * is deliberately pared back to the hygiene fixers the current tree already
 * satisfies — locking in basic cleanliness and catching drift — rather than
 * reflowing conformant code. See plan 011 for the full rationale.
 */

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . "/core/inc/bigtree/api")
	->in(__DIR__ . "/core/inc/bigtree/services")
	->name("*.php");

return (new PhpCsFixer\Config())
	->setUsingCache(false)
	->setIndent("\t") // CLAUDE.md: tabs, not spaces
	->setRules([
		"no_trailing_whitespace" => true,
		"single_blank_line_at_eof" => true,
		"line_ending" => true,
	])
	->setFinder($finder);
