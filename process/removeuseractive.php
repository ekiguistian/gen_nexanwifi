<?php
















session_start();

error_reporting(0);


$getuser = $API->comm("/ip/hotspot/active/print", array(
    "?.id" => "$removeuseractive",
));
$user = $getuser[0]['user'];

$getcookie = $API->comm("/ip/hotspot/cookie/print", array(
    "?user" => "$user",
));
$ck = $getcookie[0]['.id'];

$API->comm("/ip/hotspot/cookie/remove", array(
    ".id" => "$ck",
));

$API->comm("/ip/hotspot/active/remove", array(
    ".id" => "$removeuseractive",
));

echo "<script>window.location='./?hotspot=active&session=" . $session . "'</script>";
?>
