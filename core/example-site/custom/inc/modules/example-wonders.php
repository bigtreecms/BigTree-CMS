<?
	class ExampleWonders extends BigTreeModule {

		var $Table = "example_wonders";
		var $Module = "1";
		
		function getCurrent() {
			return $this->get(sqlfetch(sqlquery("SELECT * FROM example_wonders WHERE date <= NOW() ORDER BY date DESC LIMIT 1")));
		}
	}
?>
