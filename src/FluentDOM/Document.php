<?php
/**
 * FluentDOM\Document extends PHPs DOMDocument class. It adds some generic namespace handling on
 * the document level and registers extended Node classes for convenience.
 *
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @copyright Copyright (c) 2009-2014 Bastian Feder, Thomas Weinert
 */

namespace FluentDOM {

  /**
   * @property Element $documentElement
   */
  class Document extends \DOMDocument {

    /**
     * @var Xpath
     */
    private $_xpath = NULL;

    /**
     * @var array
     */
    private $_namespaces = [];

    /**
     * @var array
     */
    private $_reserved = [
      'xml' => 'http://www.w3.org/XML/1998/namespace',
      'xmlns' => 'http://www.w3.org/2000/xmlns/'
    ];

    /**
     * @param string $version
     * @param string $encoding
     */
    public function __construct($version = '1.0', $encoding = 'UTF-8') {
      parent::__construct($version, $encoding);
      $this->registerNodeClass('DOMAttr', __NAMESPACE__.'\\Attribute');
      $this->registerNodeClass('DOMCdataSection', __NAMESPACE__.'\\CdataSection');
      $this->registerNodeClass('DOMComment', __NAMESPACE__.'\\Comment');
      $this->registerNodeClass('DOMElement', __NAMESPACE__.'\\Element');
      $this->registerNodeClass('DOMText', __NAMESPACE__.'\\Text');
    }

    /**
     * Generate an xpath instance for the document, if the document of the
     * xpath instance does not match the document, regenerate it.
     *
     * @return Xpath
     */
    public function xpath() {
      if (isset($this->_xpath) && $this->_xpath->document == $this) {
        return $this->_xpath;
      }
      $this->_xpath = new Xpath($this);
      foreach ($this->_namespaces as $prefix => $namespace) {
        $this->_xpath->registerNamespace($prefix, $namespace);
      }
      return $this->_xpath;
    }

    /**
     * register a namespace prefix for the document, it will be used in
     * createElement and setAttribute
     *
     * @param string $prefix
     * @param string $namespace
     * @throws \LogicException
     */
    public function registerNamespace($prefix, $namespace) {
      $prefix = $this->validatePrefix($prefix);
      if (isset($this->_reserved[$prefix])) {
        throw new \LogicException(
          sprintf('Can not register reserved namespace prefix "%s".', $prefix)
        );
      }
      $this->_namespaces[$prefix] = $namespace;
      if (isset($this->_xpath) && $prefix !== '#default') {
        $this->_xpath->registerNamespace($prefix, $namespace);
      }
    }

    /**
     * Get the namespace for a given prefix
     *
     * @param string $prefix
     * @throws \LogicException
     * @return string
     */
    public function getNamespace($prefix) {
      $prefix = $this->validatePrefix($prefix);
      if (isset($this->_reserved[$prefix])) {
        return $this->_reserved[$prefix];
      }
      if (isset($this->_namespaces[$prefix])) {
        return $this->_namespaces[$prefix];
      }
      if ($prefix === '#default') {
        return '';
      }
      throw new \LogicException(
        sprintf('Unknown namespace prefix "%s".', $prefix)
      );
    }

    /**
     * @return array
     */
    public function getNamespaces() {
      return $this->_namespaces;
    }

    /**
     * @param string $prefix
     * @return string
     */
    private function validatePrefix($prefix) {
      return empty($prefix) ? '#default' : $prefix;
    }

    /**
     * If here is a ':' in the element name, consider it a namespace prefix
     * registered on the document.
     *
     * Allow to add a text content and attributes directly.
     *
     * If $content is an array, the $content argument  will be merged with the $attributes
     * argument.
     *
     * @param string $name
     * @param string|array $content
     * @param array $attributes
     * @throws \LogicException
     * @return Element
     */
    public function createElement($name, $content = NULL, array $attributes = NULL) {
      list($prefix, $localName) = QualifiedName::split($name);
      $namespace = '';
      if ($prefix !== FALSE) {
        if (empty($prefix)) {
          $name = $localName;
        } else {
          if (isset($this->_reserved[$prefix])) {
            throw new \LogicException(
              sprintf('Can not use reserved namespace prefix "%s" in element name.', $prefix)
            );
          }
          $namespace = $this->getNamespace($prefix);
        }
      } else {
        $namespace = $this->getNamespace('#default');
      }
      if ($namespace != '') {
        $node = parent::createElementNS($namespace, $name);
      } elseif (isset($this->_namespaces['#default'])) {
        $node = parent::createElementNS('', $name);
      } else {
        $node = parent::createElement($name);
      }
      $node = $this->ensureElement($node);
      $this->appendAttributes($node, $content, $attributes);
      $this->appendContent($node, $content);
      return $node;
    }

    /**
     * @param string $namespaceURI
     * @param string $qualifiedName
     * @param string|null $content
     * @return Element
     */
    public function createElementNS($namespaceURI, $qualifiedName, $content = null) {
      $node = $this->ensureElement(
        parent::createElementNS($namespaceURI, $qualifiedName)
      );
      $this->appendContent($node, $content);
      return $node;
    }

    /**
     * If here is a ':' in the attribute name, consider it a namespace prefix
     * registered on the document.
     *
     * Allow to add a attribute value directly.
     *
     * @param string $name
     * @param string|null $value
     * @return \DOMAttr
     */
    public function createAttribute($name, $value = NULL) {
      list($prefix) = QualifiedName::split($name);
      if ($prefix) {
        $node = parent::createAttributeNS($this->getNamespace($prefix), $name);
      } else {
        $node = parent::createAttribute($name);
      }
      if (isset($value)) {
        $node->value = $value;
      }
      return $node;
    }

    /**
     * Overload appendElement to add a text content and attributes directly.
     *
     * @param string $name
     * @param string $content
     * @param array $attributes
     * @return Element
     */
    public function appendElement($name, $content = '', array $attributes = NULL) {
      $this->appendChild(
        $node = $this->createElement($name, $content, $attributes)
      );
      return $node;
    }

    /**
     * Evaluate an xpath expression on the document.
     *
     * @param string $expression
     * @param \DOMNode $context
     * @return mixed
     */
    public function evaluate($expression, \DOMNode $context = NULL) {
      return $this->xpath()->evaluate(
        $expression, isset($context) ? $context : NULL
      );
    }

    /**
     * Put the document node into a FluentDOM\Query
     * and call find() on it.
     *
     * @param string $expression
     * @return Query
     */
    public function find($expression) {
      return \FluentDOM::Query($this)->find($expression);
    }

    /**
     * This is workaround for issue
     *
     * @param \DOMElement $node
     * @return Element
     */
    private function ensureElement(\DOMElement $node) {
      // @codeCoverageIgnoreStart
      if (!($node instanceof Element)) {
        return $node->ownerDocument->importNode($node, TRUE);
      }
      // @codeCoverageIgnoreEnd
      return $node;
    }

    /**
     * @param \DOMElement $node
     * @param string|array|NULL $content
     * @param array|NULL $attributes
     */
    private function appendAttributes($node, $content = NULL, array $attributes = NULL) {
      if (is_array($content)) {
        $attributes = NULL === $attributes
          ? $content : array_merge($content, $attributes);
      }
      if (!empty($attributes)) {
        foreach ($attributes as $attributeName => $attributeValue) {
          $node->setAttribute($attributeName, $attributeValue);
        }
      }
    }

    /**
     * @param \DOMElement $node
     * @param string|array|NULL $content
     */
    private function appendContent($node, $content = NULL) {
      if (!(empty($content) || is_array($content))) {
        $node->appendChild($this->createTextNode($content));
      }
    }
  }
}