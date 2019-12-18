<?php

abstract class pQueryTestCase extends PHPUnit_Framework_TestCase {
    /// Helpers ///

    protected function assertHtmlStringEqualsHtmlString($expected, $actual) {
        $expected = $this->normalizeHtml($expected);
        $actual = $this->normalizeHtml($actual);

        $this->assertEquals($expected, $actual);
    }

    protected function normalizeHtml($html) {
        // Remove multiple whitespace characters.
        $html = preg_replace('`\s+`', ' ', $html);

        // Tidy the html.
        $html_opts = array(
            'tidy' => '2s2n'
        );
        $html = trim(htmLawed($html, $html_opts));
        return $html;
    }
}

