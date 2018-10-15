<?php
namespace VertigoLabs\DoctrineFullTextPostgres\ORM\Query\AST\Functions;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\SqlWalker;

class TsQueryFunction extends TSFunction
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        $fulltextVectorMapping = $this->getFulltextVectorMapping($sqlWalker);

        if ($fulltextVectorMapping['languageProperty'] === null) {
            $languageSql = $sqlWalker->walkStringPrimary($fulltextVectorMapping['language']);
        } else {
            $languageExpression = clone $this->ftsField;
            $languageExpression->field = $fulltextVectorMapping['languageProperty'];
            $languageSql = $languageExpression->dispatch($sqlWalker);
        }

        return sprintf(
            '%s @@ to_tsquery(%s::regconfig, %s)',
            $this->ftsField->dispatch($sqlWalker),
            $languageSql,
            $this->queryString->dispatch($sqlWalker)
        );
    }
    
    private function getFulltextVectorMapping(SqlWalker $sqlWalker): array
    {
        $class = $sqlWalker->getQueryComponent($this->ftsField->identificationVariable);
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $class['metadata'];
        $mapping = $classMetadata->fieldMappings[$this->ftsField->field] ?? null;

        if (($mapping['type'] ?? null) !== 'tsvector') {
            throw new \LogicException(sprintf(
                'Cannot find fulltext configuration for property "%s" of class "%s".',
                $this->ftsField->field,
                $classMetadata->name
            ));
        }

        return $mapping;
    }
}
