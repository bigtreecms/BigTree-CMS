<?php

/**
 * Tests IQuery->prop() and related methods.
 */
class PropsTest extends pQueryTestCase {
    /// Tests ///

    public function testTagname() {
        $dom = $this->getDom();

        $this->assertEquals('h1', $dom('h1')->prop('tagName'));

        $text = $dom->query('p.first')->prop('tagName', 'div')->text();
        $this->assertEquals($dom->query('div.first')->text(), $text);
    }

    public function testChecked() {
        $dom = $this->getDom();

        // Check a specific checkbox we know is checked.
        $q = $dom->query('input[name="tell"]');
        $checked = $q->prop('checked');
        $this->assertTrue($checked);

        // Check all checked items.
        $q = $dom->query(':checked');
        $this->assertTrue($q->prop('checked'));

        // Uncheck the items.
        $q->prop('checked', false);
        foreach ($q as $node) {
            $this->assertFalse($node->prop('checked'));
        }
    }

    /**
     * Basic tests of val.
     */
    public function testVal() {
        $dom = $this->getDom();

        $textbox = $dom->query('[name="animal"]');
        $this->assertEquals('', $textbox->val());

        $textbox->val('dog');
        $this->assertEquals('dog', $textbox->val());
    }

    public function testValCheckbox() {
        $dom = $this->getDom();

        $checkbox = $dom->query('[name="tell"]');
        $this->assertNotEmpty($checkbox);

        // Test setting/getting.
        $checkbox->val('do');
        $this->assertEquals('do', $checkbox->val());
        $this->assertEquals('do', $checkbox->attr('value'));
        $this->assertTrue($checkbox->prop('checked'));

        // Test unchecking.
        $checkbox->val(false);
        $this->assertFalse($checkbox->prop('checked'));
        
        // Test rechecking.
        $checkbox->prop('checked', true);
        $this->assertEquals('do', $checkbox->val());
        $this->assertEquals('do', $checkbox->attr('value'));
    }

//    public function testValRadios() {
//        $dom = $this->getDom();
//    }

    public function testValSelect() {
        $dom = $this->getDom();

        $select = $dom->query('[name="house"]');
        $this->assertNotEmpty($select);

        // Set and get a value we know exists.
        $select->val('mansion');
        $this->assertEquals('mansion', $select->val());
        $this->assertEmpty($select->attr('value')); // make sure the value wasn't just set on select

        // Make sure a value is selected.
        $opt = $dom->query('option[value="mansion"]');
        $this->assertTrue($opt->prop('selected'));
        // Make sure only one value is selected.
        $selected = $select[0]->query(':selected');
        $this->assertEquals(1, $selected->count());

        // Set and get a value we know doesn't exist.
        $select->val('van');
        $this->assertEmpty($select->val());
    }

    public function testValTextarea() {
        $dom = $this->getDom();

        // Test setting and getting the value.
        $textarea = $dom->query('[name="about"]');
        $this->assertNotEmpty($textarea);
        $textarea->val('Test');
        $this->AssertEquals('Test', $textarea->val());

        // Test setting and getting html.
        $textarea->val('This is <b>bold</b>.');
        $this->AssertEquals('This is <b>bold</b>.', $textarea->val());
    }

    /// Helpers ///

    protected function getDom() {
        $dom = pQuery::parseFile(__DIR__.'/test-file.html');
        return $dom;
    }
}