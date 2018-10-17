<?php
/**
 * @author: James Murray <jaimz@vertigolabs.org>
 * @copyright:
 * @date: 9/15/2015
 * @time: 3:12 PM
 */

namespace VertigoLabs\DoctrineFullTextPostgres\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Class TsVector
 * @package VertigoLabs\DoctrineFullTextPostgres\DBAL\Types
 * @todo figure out how to get the weight into the converted sql code
 */
class TsVector extends Type
{
    const NAME = 'tsvector';

    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param array $fieldDeclaration The field declaration.
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform The currently used database platform.
     *
     * @return string
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'tsvector';
    }
    
    /**
     * Gets the name of this type.
     *
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform)
    {
        return ['tsvector'];
    }
}
