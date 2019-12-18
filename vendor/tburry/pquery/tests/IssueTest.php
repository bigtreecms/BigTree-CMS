<?php

/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license LGPLv2
 */

/**
 * Contains tests for github issues.
 */
class IssueTest extends pQueryTestCase {
    /**
     * Test attributes with no quotes.
     *
     * @link https://github.com/tburry/pquery/issues/4
     */
    public function testAttrWithoutQuotes() {
        $html = '<a href=/index.php/example>Example</a>';
        $dom = pQuery::parseStr($html);

        $this->assertSame('/index.php/example', $dom->query('a')->attr('href'));
    }

    /**
     * Make sure that the {@link pQuery} class is still {@link Countable}.
     *
     * @link https://github.com/tburry/pquery/issues/8
     */
    public function testStillCountable() {
        $html = 'Is <b>this</b> what you <b>want</b>?';
        $dom = pQuery::parseStr($html);

        $pq = $dom->query('b');

        $this->assertTrue($pq instanceof \pQuery\IQuery);
        $this->assertTrue($pq instanceof \Countable);
        $count = count($pq);
        $this->assertSame(2, $count);
    }
}
