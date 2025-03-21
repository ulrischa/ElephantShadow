<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
require '../ElephantShadow.php';
$el = new ElephantShadow(__DIR__ . '/templates/', __DIR__ . '/css/', __DIR__ . '/js/');
$el->init();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My SSR Page</title>
</head>
<body>
  <!-- Custom elements in your page -->
  <my-component message="Hello from SSR">
    <p slot="default">This is additional slot content.</p>
  </my-component>

</body>
</html>