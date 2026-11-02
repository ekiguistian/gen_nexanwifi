<?php
















session_start();

error_reporting(0);


$API->comm("/ip/hotspot/cookie/remove", array(
    ".id" => "$removecookie",
));

echo "<script>window.location='./?hotspot=cookies&session=" . $session . "'</script>";
?>
