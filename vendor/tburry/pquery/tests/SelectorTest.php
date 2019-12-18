<?php

class SelectorTest extends pQueryTestCase {
    public function testAttributeContainsPrefix() {
        $dom = $this->getDom();

        $q = $dom->query('[lang|=en]');
        $this->assertEquals(2, $q->count());
    }

    public function testId() {
        $dom = $this->getDom();

        $this->assertEquals('body', $dom->query('#body')->attr('id'));
        $this->assertEquals('body', $dom->query('body#body')->attr('id'));

        $this->assertEquals('moon-base', $dom->query('#house')->val());
    }

    public function testText() {
        $html = <<<HTML
<h1>foo bar</h1>
HTML;

        $dom = pQuery::parseStr($html);
        $q = $dom->query('text()');

        $this->assertSame(1, $q->count());
        $this->assertSame('foo bar', $q->text());
        $this->assertSame('foo bar', $q->html());

        $q->text('hello world');
        $this->assertSame('hello world', $q->text());
    }

    /// Helpers ///

    /**
     *
     * @return pQuery\DomNode
     */
    protected function getDom() {
        $dom = pQuery::parseFile(__DIR__.'/test-file.html');
        return $dom;
    }
}

