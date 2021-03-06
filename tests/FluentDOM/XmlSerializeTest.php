<?php
namespace FluentDOM {

  require_once(__DIR__.'/TestCase.php');

  abstract class XmlSerialize_TestProxy implements XmlSerializable {

    use XmlSerialize;

  }

  abstract class XmlSerialize_TestProxyInvalid  {

    use XmlSerialize;

  }

  class XmlSerializeTest extends TestCase {

    /**
     * @covers FluentDOM\XmlSerialize
     */
    public function testGetXmlWithOneElement() {
      $object = $this->getMockForAbstractClass('FluentDOM\\XmlSerialize_TestProxy');
      $object
        ->expects($this->once())
        ->method('appendTo')
        ->will(
          $this->returnCallback(
            function (Element $parent) {
              $parent->appendElement('test');
            }
          )
        );
      $this->assertEquals('<test/>', $object->getXml());
    }

    /**
     * @covers FluentDOM\XmlSerialize
     */
    public function testGetXmlWithoutInterfaceExpectingException() {
      $object = $this->getMockForAbstractClass('FluentDOM\\XmlSerialize_TestProxyInvalid');
      $this->setExpectedException('LogicException');
      $object->getXml();
    }
  }
}