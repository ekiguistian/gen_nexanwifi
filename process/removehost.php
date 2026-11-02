<?php
















session_start();

error_reporting(0);


$API->comm("/ip/hotspot/host/remove", array(
    ".id" => "$removehost",
));

echo "<script>window.location='./?hotspot=hosts&session=" . $session . "'</script>";
?>
