<?php
















session_start();

error_reporting(0);


if ($removesch != "") {
	$API->comm("/system/scheduler/remove", array(
		".id" => "$removesch",
	));

	echo "<script>window.location='./?system=scheduler&session=" . $session . "'</script>";
}

elseif ($enablesch != "") {
	$API->comm("/system/scheduler/set", array(
		".id" => "$enablesch",
		"disabled" => "no",
	));

	echo "<script>window.location='./?system=scheduler&session=" . $session . "'</script>";
}


elseif ($disablesch != "") {
	$API->comm("/system/scheduler/set", array(
		".id" => "$disablesch",
		"disabled" => "yes",
	));

	echo "<script>window.location='./?system=scheduler&session=" . $session . "'</script>";
}
