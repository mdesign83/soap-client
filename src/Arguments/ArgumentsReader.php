<?php
namespace GoetasWebservices\SoapServices\SoapClient\Arguments;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\Instantiator\Instantiator;
use JMS\Serializer\Serializer;

class ArgumentsReader implements ArgumentsReaderInterface
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param array $args
     * @param array $input
     * @return null|object
     */
    public function readArguments(array $args, array $input)
    {
        if (!count($input['parts'])) {
            return null;
        }

        $instantiator = new Instantiator();
        $factory = $this->serializer->getMetadataFactory();
        $instance = $instantiator->instantiate($input['part_fqcn']);
        $classMetadata = $factory->getMetadataForClass($input['part_fqcn']);

        if (count($input['parts']) > 1) {

            if (count($input['parts']) !== count($args)) {
                throw new \Exception("Expected to have exactly " . count($input['parts']) . " arguments, supplied " . count($args));
            }

            foreach ($input['parts'] as $paramName) {
                //@todo $propertyName should use the xsd2php naming strategy (or do in the metadata extractor)
                $propertyName = Inflector::camelize(str_replace(".", " ", $paramName));
                $propertyMetadata = $classMetadata->propertyMetadata[$propertyName];
                $propertyMetadata->setValue($instance, array_shift($args));
            }
            return $instance;
        }

        $propertyName = Inflector::camelize(str_replace(".", " ", reset($input['parts'])));
        $propertyMetadata = $classMetadata->propertyMetadata[$propertyName];
        if ($args[0] instanceof $propertyMetadata->type['name']) {
            $propertyMetadata->setValue($instance, reset($args));
            return $instance;
        }

        $instance2 = $instantiator->instantiate($propertyMetadata->type['name']);
        $classMetadata2 = $factory->getMetadataForClass($propertyMetadata->type['name']);
        $propertyMetadata->setValue($instance, $instance2);

        foreach ($classMetadata2->propertyMetadata as $propertyMetadata2) {
            if (!count($args)) {
                throw new \Exception("Not enough arguments provided. Can't fina a parameter to set " . $propertyMetadata2->name);
            }
            $value = array_pop($args);
            $propertyMetadata2->setValue($instance2, $value);
        }
        return $instance;
    }
}
