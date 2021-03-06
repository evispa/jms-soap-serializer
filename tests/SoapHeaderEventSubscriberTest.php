<?php

namespace DMT\Test\Soap\Serializer;

use DMT\Soap\Serializer\SoapDateHandler;
use DMT\Soap\Serializer\SoapHeaderEventSubscriber;
use DMT\Soap\Serializer\SoapMessageEventSubscriber;
use DMT\Soap\Serializer\SoapNamespaceInterface;
use DMT\Soap\Serializer\SoapSerializationVisitor;
use DMT\Soap\Serializer\SoapSerializationVisitorFactory;
use DMT\Test\Soap\Serializer\Fixtures\HeaderLogin;
use DMT\Test\Soap\Serializer\Fixtures\Language;
use Doctrine\Common\Annotations\AnnotationRegistry;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use JMS\Serializer\Handler\HandlerRegistry;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializerBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class SoapHeaderEventSubscriberTest
 *
 * @package DMT\Soap\Serializer
 */
class SoapHeaderEventSubscriberTest extends TestCase
{
    /**
     * Test the SOAP Header is added, when provided.
     */
    public function testAddingSoapHeader()
    {
        AnnotationRegistry::registerUniqueLoader('class_exists');

        $serializer = SerializerBuilder::create()
            ->setSerializationVisitor('soap', new SoapSerializationVisitorFactory())
            ->setPropertyNamingStrategy(
                new SerializedNameAnnotationStrategy(
                    new IdenticalPropertyNamingStrategy()
                )
            )
            ->configureListeners(
                function (EventDispatcher $dispatcher) {
                    $dispatcher->addSubscriber(
                        new SoapMessageEventSubscriber()
                    );
                    $dispatcher->addSubscriber(
                        new SoapHeaderEventSubscriber(
                            new HeaderLogin('dummy', 'secret123!')
                        )
                    );
                }
            )
            ->configureHandlers(
                function (HandlerRegistry $registry) {
                    $registry->registerSubscribingHandler(new SoapDateHandler());
                }
            )
            ->build();

        $xml = simplexml_load_string(
            $serializer->serialize(new Language('Python', 33, new \DateTime('1994-01-25')), 'soap')
        );

        static::assertContains(SoapNamespaceInterface::SOAP_NAMESPACES[SOAP_1_1], $xml->getNamespaces());
        static::assertSame('Envelope', $xml->getName());
        static::assertSame('Header', $xml->xpath('/*[local-name()="Envelope"]/*')[0]->getName());
        static::assertSame('Body', $xml->xpath('/*[local-name()="Envelope"]/*')[1]->getName());

        $header = $xml->xpath('//*[local-name()="Header"]/*')[0]->children();
        static::assertSame('dummy', strval($header->username));
        static::assertSame('secret123!', strval($header->password));
    }
}
