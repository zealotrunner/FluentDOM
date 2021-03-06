<?php
/**
* @license http://www.opensource.org/licenses/mit-license.php The MIT License
* @copyright Copyright (c) 2009-2014 Bastian Feder, Thomas Weinert
*/
header('Content-type: text/plain');
require_once('../vendor/autoload.php');

$html = <<<HTML
<html>
  <head>
    <title>Examples: FluentDOM\Query::appendTo()</title>
  </head>
  <body>
    <span>I have nothing more to say... </span>
    <div id="foo">FOO! </div>
  </body>
</html>
HTML;

echo FluentDOM($html)
  ->find('//span')
  ->appendTo('//div[@id = "foo"]');