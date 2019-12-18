<?php

class BasicTest extends pQueryTestCase {

    /// Tests ///

    public function testAttributes() {
        $html = 'If you want some help go <a href="/help.html">here</a> or <a href="/morehelp.html">here</a>.';
        $dom = pQuery::parseStr($html);

        foreach ($dom->query('a') as $a) {
            $a->attr('href', 'http://example.com/'.ltrim($a->attr('href'), '/'));
        }

        $expected = 'If you want some help go <a href="http://example.com/help.html">here</a> or <a href="http://example.com/morehelp.html">here</a>.';
        $actual = $dom->html();

        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    public function testHelloWorld() {
        $html = '<div class="container">
            <div class="inner verb">Hello</div>
            <div class="inner adj">Cruel</div>
            <div class="inner obj">World</div>
          </div>';


        $dom = pQuery::parseStr($html);

        $dom->query('.inner')
                ->tagName('span');

        $dom->query('.adj')
                ->html('Beautiful')
                ->tagName('i');

        $expected = '<div class="container">
            <span class="inner verb">Hello</span>
            <i class="inner adj">Beautiful</i>
            <span class="inner obj">World</span>
          </div>';

        $this->assertHtmlStringEqualsHtmlString($expected, $dom->html());
    }
}
