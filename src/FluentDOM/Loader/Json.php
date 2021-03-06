<?php
/**
 * Load a DOM document from a json string or file
 *
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @copyright Copyright (c) 2009-2014 Bastian Feder, Thomas Weinert
 */

namespace FluentDOM\Loader {

  use FluentDOM\Document;
  use FluentDOM\Loadable;

  /**
   * Load a DOM document from a json string or file
   */
  class Json implements Loadable {

    use Supports;

    const XMLNS = 'urn:carica-json-dom.2013';
    const DEFAULT_QNAME = '_';

    const OPTION_VERBOSE = 1;

    const TYPE_NULL = 'null';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_NUMBER = 'number';
    const TYPE_STRING = 'string';
    const TYPE_OBJECT = 'object';
    const TYPE_ARRAY = 'array';

    /**
     * JSON errors
     * @var array $_jsonErrors
     */
    private $_jsonErrors = array(
      -1 => 'Unknown error has occurred',
      0 => 'No error has occurred',
      1 => 'The maximum stack depth has been exceeded',
      3 => 'Control character error, possibly incorrectly encoded',
      4 => 'Syntax error',
    );

    /**
     * Maximum recursions
     *
     * @var int
     */
    private $_recursions = 100;

    /**
     * Add json:type and json:name attributes to all elements, even if not necessary.
     *
     * @var bool
     */
    private $_verbose = FALSE;

    /**
     * Create the loader for a json string.
     *
     * The string will be decoded into a php variable structure and convert into a DOM document
     * If options contains is self::OPTION_VERBOSE, the DOMNodes will all have
     * json:type and json:name attributes. Even if the information could be read from the structure.
     *
     * @param int $options
     * @param int $depth
     */
    public function __construct($options = 0, $depth = 100) {
      $this->_recursions = (int)$depth;
      $this->_verbose = ($options & self::OPTION_VERBOSE) == self::OPTION_VERBOSE;
    }

    /**
     * @return string[]
     */
    public function getSupported() {
      return array('json', 'application/json', 'text/json');
    }


    /**
     * Load the json string into an DOMDocument
     *
     * @param mixed $source
     * @param string $contentType
     * @throws \UnexpectedValueException
     * @return Document|NULL
     */
    public function load($source, $contentType) {
      $json = $source;
      if (is_string($source)) {
        $json = $this->parseString($source);
      }
      if ($json || is_array($json)) {
        $dom = new Document('1.0', 'UTF-8');
        $dom->appendChild(
          $root = $dom->createElementNS(self::XMLNS, 'json:json')
        );
        $this->transferTo($root, $json, $this->_recursions);
        return $dom;
      }
      return NULL;
    }

    /**
     * @param string $source
     * @throws \UnexpectedValueException
     * @return mixed
     */
    private function parseString($source)  {
      $json = FALSE;
      if (!$this->startsWith($source, '{[')) {
        $source = file_get_contents($source);
      }
      if ($this->startsWith($source, '{[')) {
        $json = json_decode($source);
        if (!($json || is_array($json))) {
          $code = is_callable('json_last_error') ? json_last_error() : -1;
          throw new \UnexpectedValueException($this->_jsonErrors[$code]);
        }
      }
      return $json;
    }

    /**
     * Transfer a value into a target xml element node. This sets attributes on the
     * target node and creates child elements for object and array values.
     *
     * If the current element is an object or array the method is called recursive.
     * The $recursions parameter is used to limit the recursion depth of this function.
     *
     * @param \DOMElement $target
     * @param $value
     * @param int $recursions
     */
    private function transferTo(\DOMElement $target, $value, $recursions = 100) {
      if ($recursions < 1) {
        return;
      }
      $type = $this->getTypeFromValue($value);
      switch ($type) {
      case self::TYPE_ARRAY :
        $this->transferArrayTo($target, $value, $this->_recursions - 1);
        break;
      case self::TYPE_OBJECT :
        $this->transferObjectTo($target, $value, $this->_recursions - 1);
        break;
      default :
        if ($this->_verbose || $type != self::TYPE_STRING) {
          $target->setAttributeNS(self::XMLNS, 'json:type', $type);
        }
        $string = $this->getValueAsString($type, $value);
        if (is_string($string)) {
          $target->appendChild($target->ownerDocument->createTextNode($string));
        }
      }
    }

    /**
     * Get the type from a variable value.
     *
     * @param mixed $value
     * @return string
     */
    public function getTypeFromValue($value) {
      if (is_array($value)) {
        return self::TYPE_ARRAY;
      } elseif (is_object($value)) {
        return self::TYPE_OBJECT;
      } elseif (is_null($value)) {
        return self::TYPE_NULL;
      } elseif (is_bool($value)) {
        return self::TYPE_BOOLEAN;
      } elseif (is_int($value) || is_float($value)) {
        return self::TYPE_NUMBER;
      } else {
        return self::TYPE_STRING;
      }
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return null|string
     */
    public function getValueAsString($type, $value) {
      switch ($type) {
      case self::TYPE_NULL :
        return NULL;
      case self::TYPE_BOOLEAN :
        return $value ? 'true' : 'false';
      default :
        return (string)$value;
      }
    }

    /**
     * Transfer an array value into a target element node. Sets the json:type attribute to 'array' and
     * creates child element nodes for each array element using the default QName.
     *
     * @param \DOMElement $target
     * @param array $value
     * @param int $recursions
     */
    private function transferArrayTo(\DOMElement $target, array $value, $recursions) {
      $target->setAttributeNS(self::XMLNS, 'json:type', 'array');
      foreach ($value as $item) {
        $target->appendChild(
          $child = $target->ownerDocument->createElement(self::DEFAULT_QNAME)
        );
        $this->transferTo($child, $item, $recursions);
      }
    }

    /**
     * Transfer an object value into a target element node. If the object has no properties,
     * the json:type attribute is always set to 'object'. If verbose is not set the json:type attribute will
     * be omitted if the object value has properties.
     *
     * The method creates child nodes for each property. The property name will be normalized to a valid NCName.
     * If the normalized NCName is different from the property name or verbose is TRUE, a json:name attribute
     * with the property name will be added.
     *
     * @param \DOMElement $target
     * @param object $value
     * @param int $recursions
     */
    private function transferObjectTo(\DOMElement $target, $value, $recursions) {
      $properties = get_object_vars($value);
      if ($this->_verbose || empty($properties)) {
        $target->setAttributeNS(self::XMLNS, 'json:type', 'object');
      }
      foreach ($properties as $property => $item) {
        $qname = $this->normalizeKey($property);
        $target->appendChild(
          $child = $target->ownerDocument->createElement($qname)
        );
        if ($this->_verbose || $qname != $property) {
          $child->setAttributeNS(self::XMLNS, 'json:name', $property);
        }
        $this->transferTo($child, $item, $recursions);
      }
    }

    /**
     * Removes all characters from a json key string that are not allowed in a xml NCName. An NCName is the
     * tag name of an xml element without a prefix.
     *
     * If the result of that removal is an empty string, the default QName is returned.
     *
     * @param string $key
     * @return string
     */
    private function normalizeKey($key) {
      $nameStartChar =
        'A-Z_a-z'.
        '\\x{C0}-\\x{D6}\\x{D8}-\\x{F6}\\x{F8}-\\x{2FF}\\x{370}-\\x{37D}'.
        '\\x{37F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}'.
        '\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}'.
        '\\x{FDF0}-\\x{FFFD}\\x{10000}-\\x{EFFFF}';
      $nameAdditionalChar =
        $nameStartChar.
        '\\.\\d\\x{B7}\\x{300}-\\x{36F}\\x{203F}-\\x{2040}';
      $result = preg_replace(
        array(
          '([^'.$nameAdditionalChar.'-]+)u',
          '(^[^'.$nameStartChar.']+)u',
        ),
        '',
        $key
      );
      return (empty($result)) ? self::DEFAULT_QNAME : $result;
    }
  }
}