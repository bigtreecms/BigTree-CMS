<?
	if (is_numeric($id)) {
		BigTreeAutoModule::recacheItem($id,$table);
	} else {
		BigTreeAutoModule::recacheItem(substr($id,1),$table,true);
	}
?>