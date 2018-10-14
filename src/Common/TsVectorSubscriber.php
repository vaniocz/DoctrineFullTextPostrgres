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

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /** @var ClassMetadata $metaData */
        $metaData = $eventArgs->getClassMetadata();

        $class = $metaData->getReflectionClass();
        foreach ($class->getProperties() as $prop) {
            /** @var TsVector $annotation */
            $annotation = $this->reader->getPropertyAnnotation($prop, self::ANNOTATION_NS . self::ANNOTATION_TSVECTOR);
            if (is_null($annotation)) {
                continue;
            }
            $annotation->fields = $this->normalizeFields($annotation->fields);
            $this->checkWatchFields($class, $annotation);
            $metaData->mapField([
                'fieldName' => $prop->getName(),
                'columnName' => $this->getColumnName($prop, $annotation),
                'type' => 'tsvector',
                'language' => strtolower($annotation->language),
                'fields' => $annotation->fields
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

    private function preFlush(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $metadata = $args->getObjectManager()->getClassMetadata(get_class($entity));

        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($metadata->getFieldNames() as $prop) {
            $field = $metadata->getFieldMapping($prop);

            if ($field['type'] !== 'tsvector') {
                continue;
            }

            if ($args instanceof PreUpdateEventArgs) {
                if (!array_intersect_key($field['fields'], $args->getEntityChangeSet())) {
                    continue;
                }
            }

            $connection = $args->getEntityManager()->getConnection();
            $fields = [];
            $parameters = [];
            foreach ($field['fields'] as $fieldName => $weight) {
                $text = $accessor->getValue($entity, $fieldName);
                if ($text) {
                    $fields[] = "setweight(coalesce(to_tsvector(?, ?), ''), ?)";
                    $parameters[] = $field['language'];
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

    private function getColumnName(\ReflectionProperty $property, TsVector $annotation)
    {
        $name = $annotation->name;
        if (is_null($name)) {
            $name = $property->getName();
        }
        return $name;
    }

    private function checkWatchFields(\ReflectionClass $class, TsVector $annotation)
    {
        foreach ($annotation->fields as $fieldName => $weight) {
            if (!$class->hasProperty($fieldName) && !$class->hasMethod('get' . ucfirst($fieldName))) {
                throw new MappingException(sprintf('Class does not contain %s property', $fieldName));
            }
        }
    }

    private function normalizeFields($fields, $defaultWeight = 'A')
    {
        $normalizedFields = [];
        foreach ($fields as $key => $field) {
            if (in_array($field, ['A', 'B', 'C', 'D'])) {
                $normalizedFields[$key] = $field;
            } else {
                $normalizedFields[$field] = $defaultWeight;
            }
        }

        return $normalizedFields;
    }
}
