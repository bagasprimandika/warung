<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
header('Location: cart.php#checkout');
exit;
?>
