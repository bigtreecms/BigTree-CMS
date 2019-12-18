<?php

/**
 * Test the {@link pQuery object}
 */
class pQueryTest extends pQueryTestCase {
    /// Properties ///
    protected $testHtml = <<<EOT
<div class="container">
<h1>Hello World!</h1>
<p class="first">This is a very <b>special</b> message.</p>
<p class="second">And I am <i>giving</i> it to you.</p>
</div>
EOT;

    /// Test Cases ///

    public function testAddClass() {
        $dom = $this->getDom();

        $dom->query('b,i')->addClass('snappy');
        $dom->query('p')->addClass('lead');

        $expected = '<div class="container">
            <h1>Hello World!</h1>
            <p class="first lead">This is a very <b class="snappy">special</b> message.</p>
            <p class="second lead">And I am <i class="snappy">giving</i> it to you.</p>
            </div>';
        $actual = $dom->html();

        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    public function testArrayAccess() {
        $dom = $this->getDom();

        $q = $dom->query('p');

        // Test isset.
        $this->assertTrue(isset($q[0]));
        $this->assertFalse(isset($q[2]));

        // Test get.
        $expected = 'This is a very <b>special</b> message.';
        $actual = $q[0]->html();
        $this->assertHtmlStringEqualsHtmlString($expected, $actual);

        // Test set.
        $q[0] = '<div>Just testing.</div>';
        $expected = '<div class="container">
            <h1>Hello World!</h1>
            <div>Just testing.</div>
            <p class="second">And I am <i>giving</i> it to you.</p>
            </div>';
        $actual = $dom->html();
        $this->assertHtmlStringEqualsHtmlString($expected, $actual);

        // Test unset.
        unset($q[1]);
        $expected = '<div class="container">
            <h1>Hello World!</h1>
            <div>Just testing.</div>
            </div>';
        $actual = $dom->html();
        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testArrayAccessBad() {
        $dom = $this->getDom();

        $q = $dom->query('p');
        $q[] = '<div>New node</div>';
    }

    public function testAttrGet() {
        $dom = $this->getDom();

        $attr = $dom->query('p')->attr('class');
        $this->assertEquals('first', $attr);
    }

    public function testAttrSet() {
        $dom = $this->getDom();
        $dom->query('p')->attr('data-ex', 'slug');

        $expected = '<div class="container">
            <h1>Hello World!</h1>
            <p class="first" data-ex="slug">This is a very <b>special</b> message.</p>
            <p class="second" data-ex="slug">And I am <i>giving</i> it to you.</p>
            </div>';
        $actual = $dom->html();

        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    public function testClear() {
        $dom = $this->getDom();

        $dom->query('h1')->clear();
        $dom->query('p')->clear();

        $expected = '<div class="container">
            <h1></h1>
            <p class="first"></p>
            <p class="second"></p>
            </div>';
        $actual = $dom->html();

        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    public function testCount() {
        $dom = $this->getDom();

        $count = $dom->query('p')->count();
        $this->assertEquals(2, $count);
    }

    public function testHasClass() {
        $dom = $this->getDom();

        // Test false.
        $this->assertFalse($dom->query('h1')->hasClass('foo'));

        // Test true.
        $this->assertTrue($dom->query('p')->hasClass('second'));
        $this->assertTrue($dom->query('p')->addClass('test')->hasClass('test'));
    }

    public function testParseFile() {
        $dom = pQuery::parseFile(__DIR__.'/test-file.html');

        $this->assertHtmlStringEqualsHtmlString('Hello World!', $dom('h1')->html());
        $this->assertHtmlStringEqualsHtmlString('What is your favorite animal?', $dom('label')->html());
    }

    public function testRemoveAttr() {
        $dom = $this->getDom();

        $dom->query()->removeAttr('class');

        $expected = '<div>
            <h1>Hello World!</h1>
            <p>This is a very <b>special</b> message.</p>
            <p>And I am <i>giving</i> it to you.</p>
            </div>';

        $this->assertHtmlStringEqualsHtmlString($expected, $dom->html());
    }

    public function testRemoveClass() {
        $dom = $this->getDom();

        $expected = $dom->html();
        $dom->query('p')->addClass('test')->removeClass('test');
        $this->assertHtmlStringEqualsHtmlString($expected, $dom->html());
    }

    public function testReplaceWith() {
        $dom = $this->getDom();

        $dom->query('h1,p')->replaceWith('<div>Just Testing</div>');

        $expected = '<div class="container">
                <div>Just Testing</div>
                <div>Just Testing</div>
                <div>Just Testing</div>
            </div>';
        $actual = $dom->html();

        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    public function testText() {
        $dom = $this->getDom();

        // Test empty get.
        $this->assertEquals('', $dom('foo')->text());

        // Test a simple get/set.
        $q = $dom->query('h1');
        $q->text($q->text().'!');
        $this->assertEquals('Hello World!!', $q->text());
    }

    public function testToggleClass() {
        $dom = $this->getDom();

        // Test toggling off.
        $this->assertEmpty($dom->query('p.first')->toggleClass('first')->attr('class'));

        // Test toggling on.
        $dom->query('h1')->toggleClass('test')->attr('class');
        $this->assertTrue($dom->query('h1')->hasClass('test'));

        // Test explicit toggling off.
        $this->assertFalse($dom->query()->toggleClass('first', false)->hasClass('first'));

        // Test explicit toggling on.
        $q = $dom->query('h1');
        $this->assertNotEmpty($q);
        $q->removeClass('on');
        $this->assertFalse($q->hasClass('on'));
        $q->toggleClass('on', true);
        $this->assertTrue($q->hasClass('on'));
    }

    /// Helpers ///

    /**
     *
     * @return pQuery\DomNode
     */
    protected function getDom() {
        return pQuery::parseStr($this->testHtml);
    }
}