<?php

class DomInsertionTest extends pQueryTestCase {
    /// Tests ///

    /**
     * @dataProvider provideBasic
     */
    function testBasic($func, $html, $query, $arg, $expected) {
        $dom = pQuery::parseStr($html);
        $dom->query($query)->$func($arg);
        $actual_html = $dom->html();

        $this->assertHtmlStringEqualsHtmlString($expected, $actual_html);
    }

    /**
     * @dataProvider provideRemove
     */
    public function testRemove($dom_remove = false) {
        $html = '<div class="container">
            <div class="hello">Hello</div>
            <div class="goodbye">Goodbye</div>
          </div>';

        $expected = '<div class="container">
            <div class="goodbye">Goodbye</div>
          </div>';

        $dom = pQuery::parseStr($html);

        // There are two forms of remove. Test them both.
        if ($dom_remove)
            $dom->remove('.hello');
        else
            $dom->query('.hello')->remove();

        $this->assertHtmlStringEqualsHtmlString($expected, $dom->html());
    }

    public function testUnwrap() {
        $html = '<body>
          <button>wrap/unwrap</button>
          <div>
            <p>Hello</p>
          </div>
          <div>
            <p>cruel</p>
          </div>
          <div>
            <p>World</p>
          </div>
          </body>';

        $expected = '<body>
          <button>wrap/unwrap</button>
          <p>Hello</p>
          <p>cruel</p>
          <p>World</p>
          </body>';

        $dom = pQuery::parseStr($html);
        $dom->query('p')->unwrap();

        $actual = $dom->html();
        $this->assertHtmlStringEqualsHtmlString($expected, $actual);
    }

    /**
     * @dataProvider provideWrap
     */
    public function testWrap($wrap, $expected) {
        $html = '<div class="container">
            <div class="inner">Hello</div>
            <div class="inner">Goodbye</div>
          </div>';

        $dom = pQuery::parseStr($html);
        $dom->query('.inner')->wrap($wrap);

        $this->assertHtmlStringEqualsHtmlString($expected, $dom->html());
    }

    public function testWrapInner() {
        $html = '<div class="container">
            <div class="inner">Hello</div>
            <div class="inner">Goodbye</div>
          </div>';

        $expected = '<div class="container">
            <div class="inner">
              <div class="new">Hello</div>
            </div>
            <div class="inner">
              <div class="new">Goodbye</div>
            </div>
          </div>';

        $dom = pQuery::parseStr($html);
        $dom->query('.inner')->wrapInner('<div class="new"></div>');

        $this->assertHtmlStringEqualsHtmlString($expected, $dom->html());
    }

    /// Data Providers ///

    public function provideBasic() {
        $html =
            '<h2>Greetings</h2>
            <div class="container">
              <div class="inner"> Hello </div>
              <div class="inner"> Goodbye </div>
            </div>';

        $query = '.inner';

        $arg = ' <p>Test</p> ';

        // This is an array in the form ['method' => 'expected_html']
        $tests = array(
            'after' => '<h2>Greetings</h2>
                <div class="container">
                    <div class="inner"> Hello </div>
                    <p>Test</p>
                    <div class="inner"> Goodbye </div>
                    <p>Test</p>
                </div>',
            'append' => '<h2>Greetings</h2>
              <div class="container">
                <div class="inner">
                  Hello
                  <p>Test</p>
                </div>
                <div class="inner">
                  Goodbye
                  <p>Test</p>
                </div>
              </div>',
            'before' => '<h2>Greetings</h2>
                <div class="container">
                    <p>Test</p>
                    <div class="inner"> Hello </div>
                    <p>Test</p>
                    <div class="inner"> Goodbye </div>
                </div>',
            'prepend' => '<h2>Greetings</h2>
                <div class="container">
                  <div class="inner">
                    <p>Test</p>
                    Hello
                  </div>
                  <div class="inner">
                    <p>Test</p>
                    Goodbye
                  </div>
                </div>'
            );

        $result = array();
        foreach ($tests as $func => $expected) {
            $result[$func] = array($func, $html, $query, $arg, $expected);
        }
        return $result;
    }

    public function provideRemove() {
        return array(
            'selector' => array(false),
            'dom' => array(true)
            );
    }

    public function provideWrap() {
        $tests = array(
            'string node' => array('<div class="new"></div>', '<div class="container">
                <div class="new">
                  <div class="inner">Hello</div>
                </div>
                <div class="new">
                  <div class="inner">Goodbye</div>
                </div>
              </div>'),
            'tag name' => array('div', '<div class="container">
                <div>
                  <div class="inner">Hello</div>
                </div>
                <div>
                  <div class="inner">Goodbye</div>
                </div>
              </div>'),
            );

        $result = array();
        foreach ($tests as $key => $row) {
            $result[$key] = $row;
        }
        return $result;
    }
}
