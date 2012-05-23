<?
	$calls = $_POST["calls"];
	$token = $_POST["token"];
	$response = array("success" => true,"responses" => array());
	foreach ($calls as $call) {
		$name = rtrim($call["call"],"/").".php";
		$_POST = $call["data"];
		ob_start();
		include BigTree::path("api/".$name);
		$call_response = ob_get_clean();
		$response["responses"][] = array("call" => $call["call"],"response" => $call_response);
	}
	
	echo BigTree::apiEncode($response);
?>