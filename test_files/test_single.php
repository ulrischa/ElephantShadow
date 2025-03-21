<?php
require_once '../ElephantShadow.php';

// Ressources lie next to file called
$el = new ElephantShadow(__DIR__ . '/templates/', __DIR__ . '/css/', __DIR__ . '/js/');
$rendered_component = $el->renderWebComponent(
    '<my-component message="Hello from SSR">
        <p slot="default">This is additional slot content.</p>
    </my-component>');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My SSR Page Single test</title>
</head>
<body>
<?php echo $rendered_component; ?>

</body>
</html>