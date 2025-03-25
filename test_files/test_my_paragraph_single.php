<?php
require_once '../ElephantShadow.php';

// Ressources lie next to file called
$el = new ElephantShadow(__DIR__ . '/templates/', __DIR__ . '/css/', __DIR__ . '/js/');
$rendered_component = $el->renderWebComponent(
    '<my-paragraph></my-paragraph>
');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My SSR Page Single test</title>
</head>
<body>
  <h1>Test my paragraph</h1>
<?php echo $rendered_component; ?>

</body>
</html>