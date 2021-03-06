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
    <title>Examples: FluentDOM\Query::siblings()</title>
  </head>
  <body>
    <ul>
      <li>One</li>
      <li>Two</li>
      <li class="hilite">Three</li>
      <li>Four</li>
    </ul>
    <ul>
      <li>Five</li>
      <li>Six</li>
      <li>Seven</li>
    </ul>
    <ul>
      <li>Eight</li>
      <li class="hilite">Nine</li>
      <li>Ten</li>
      <li class="hilite">Eleven</li>
    </ul>
    <p>Unique siblings: <b> </b></p>
  </body>
</html>
HTML;

echo FluentDOM($html)
  ->find('//li[@class = "hilite"]')
  ->siblings()
  ->addClass('before');
