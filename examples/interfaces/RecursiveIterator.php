<?php
header('Content-type: text/plain');

$xml = <<<XML
<html>
  <head>
    <title>Examples: FluentDOM\Query IteratorAggregate returns a RecursiveIterator</title>
  </head>
<body>
  <p>Hello</p>
  <p>cruel</p>
  <p>World</p>
</body>
</html>
XML;

require_once('../../vendor/autoload.php');

$iterator = new RecursiveIteratorIterator(
  FluentDOM($xml)->find('/*'),
  RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $key => $value) {
  echo $iterator->getDepth(), '.', $key, ': ', $value->nodeName, "\n";
}
