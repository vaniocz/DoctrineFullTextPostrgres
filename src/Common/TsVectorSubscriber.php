<?php
/**
 * @author: James Murray <jaimz@vertigolabs.org>
 * @copyright:
 * @date: 9/15/2015
 * @time: 5:18 PM
 */

namespace VertigoLabs\DoctrineFullTextPostgres\Common;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use VertigoLabs\DoctrineFullTextPostgres\ORM\Mapping\TsVector;
use \VertigoLabs\DoctrineFullTextPostgres\DBAL\Types\TsVector as TsVectorType;

/**
 * Class TsVectorSubscriber
 * @package VertigoLabs\DoctrineFullTextPostgres\Common
 */
class TsVectorSubscriber implements EventSubscriber
{
    const ANNOTATION_NS = 'VertigoLabs\\DoctrineFullTextPostgres\\ORM\\Mapping\\';
    const ANNOTATION_TSVECTOR = 'TsVector';

    /**
     * @var AnnotationReader
     */
    private $reader;

    public function __construct()
    {
        AnnotationRegistry::registerAutoloadNamespace(self::ANNOTATION_NS);
        $this->reader = new AnnotationReader();

        if (!Type::hasType(strtolower(self::ANNOTATION_TSVECTOR))) {
            Type::addType(strtolower(self::ANNOTATION_TSVECTOR), TsVectorType::class);
        }
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
            Events::prePersist,
            Events::preUpdate
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        /** @var ClassMetadata $metaData */
        $metaData = $event->getClassMetadata();

        $class = $metaData->getReflectionClass();
        foreach ($class->getProperties() as $prop) {
            /** @var TsVector $annotation */
            $annotation = $this->reader->getPropertyAnnotation($prop, self::ANNOTATION_NS . self::ANNOTATION_TSVECTOR);
            if (is_null($annotation)) {
                continue;
            }
            $annotation->properties = $this->normalizeProperties($annotation->properties);
            $this->checkWatchProperties($class, $annotation);
            $metaData->mapField([
                'fieldName' => $prop->getName(),
                'columnName' => $this->resolveColumnName($prop, $annotation, $event),
                'type' => 'tsvector',
                'language' => $annotation->language,
                'languageProperty' => $annotation->languageProperty,
                'properties' => $annotation->properties
            ]);
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $this->preFlush($args);
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->preFlush($args);
    }

    private function preFlush(LifecycleEventArgs $event)
    {
        $entity = $event->getObject();
        $metadata = $event->getObjectManager()->getClassMetadata(get_class($entity));
        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($metadata->getFieldNames() as $prop) {
            $fieldMapping = $metadata->getFieldMapping($prop);

            if ($fieldMapping['type'] !== 'tsvector') {
                continue;
            }

            if ($event instanceof PreUpdateEventArgs) {
                if (!array_intersect_key($fieldMapping['properties'], $event->getEntityChangeSet())) {
                    continue;
                }
            }

            $connection = $event->getEntityManager()->getConnection();
            $fields = [];
            $parameters = [];

            foreach ($fieldMapping['properties'] as $property => $weight) {
                $texts = $accessor->getValue($entity, $property);

                if (!is_array($texts) && !$texts instanceof \Traversable) {
                    $texts = [$texts => $weight];
                }

                foreach ($texts as $text => $weight) {
                    if ($text === null || $text === '') {
                        continue;
                    }

                    $language = $fieldMapping['languageProperty'] === null
                        ? $fieldMapping['language']
                        : $accessor->getValue($entity, $fieldMapping['languageProperty']);

                    $fields[] = "setweight(coalesce(to_tsvector(?, ?), ''), ?)";
                    $parameters[] = $language;
                    $parameters[] = $text;
                    $parameters[] = $weight;
                }
            }

            $query = 'SELECT ' . implode($fields, " || ' ' || ");
            $result = $connection->executeQuery($query, $parameters);
            $tsVector = $result->fetchColumn();

            $accessor->setValue($entity, $prop, $tsVector);
        }
    }

    private function resolveColumnName(
        \ReflectionProperty $property,
        TsVector $annotation,
        LoadClassMetadataEventArgs $event
    ) {
        if ($annotation->name === null) {
            $namingStrategy = $event->getEntityManager()->getConfiguration()->getNamingStrategy();

            return $namingStrategy->propertyToColumnName($property->getName());

        }

        return $annotation->name;
    }

    private function checkWatchProperties(\ReflectionClass $class, TsVector $annotation)
    {
        foreach ($annotation->properties as $fieldName => $weight) {
            if (!$class->hasProperty($fieldName) && !$class->hasMethod('get' . ucfirst($fieldName))) {
                throw new MappingException(sprintf('Class does not contain %s property', $fieldName));
            }
        }
    }

    private function normalizeProperties($properties, $defaultWeight = 'A')
    {
        $normalizedFields = [];

        foreach ($properties as $key => $field) {
            if (in_array($field, ['A', 'B', 'C', 'D'])) {
                $normalizedFields[$key] = $field;
            } else {
                $normalizedFields[$field] = $defaultWeight;
            }
        }

        return $normalizedFields;
    }
}
