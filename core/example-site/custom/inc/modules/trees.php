<?php

	class DemoTrees extends BigTreeModule
	{
	    public $Table = 'demo_trees';
	    public $Module = '1';

	    public function __construct()
	    {
	        global $cms;
	        $this->Link = $cms->getLink(2);
	    }

	    public function get($item)
	    {
	        $item = parent::get($item);
	        $item['detail_link'] = $this->Link.'detail/'.$item['route'].'/';

	        return $item;
	    }

	    public function getNav($page)
	    {
	        $items = $this->getAllPositioned();
	        $nav = array();
	        foreach ($items as $item) {
	            $nav[] = array('title' => $item['title'],'link' => WWW_ROOT.$page['path'].'/detail/'.$item['route'].'/');
	        }

	        return $nav;
	    }

	    public function getPrevious($tree)
	    {
	        $trees = $this->getAllPositioned();
	        $position = array_search($tree, $trees);

	        return isset($trees[$position - 1]) ? $trees[$position - 1] : false;
	    }

	    public function getNext($tree)
	    {
	        $trees = $this->getAllPositioned();
	        $position = array_search($tree, $trees);

	        return isset($trees[$position + 1]) ? $trees[$position + 1] : false;
	    }
	}
?>
