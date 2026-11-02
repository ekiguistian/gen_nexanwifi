<?php
















session_start();

error_reporting(0);

$API->comm("/ppp/active/remove", array(
    ".id" => "$removepactive",
));

echo "<script>window.location='./?ppp=active&session=" . $session . "'</script>";
