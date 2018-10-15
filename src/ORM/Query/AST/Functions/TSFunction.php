<?php
namespace VertigoLabs\DoctrineFullTextPostgres\ORM\Query\AST\Functions;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use VertigoLabs\DoctrineFullTextPostgres\ORM\Mapping\TsVector;

abstract class TSFunction extends FunctionNode
{
    /**
     * @var PathExpression
     */
    public $ftsField = null;
    /**
     * @var PathExpression
     */
    public $queryString = null;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->ftsField = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->queryString = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    protected function findFTSField(SqlWalker $sqlWalker)
    {
        $reader = new AnnotationReader();
        $dqlAlias = $this->ftsField->identificationVariable;
        $class = $sqlWalker->getQueryComponent($dqlAlias);
        /** @var ClassMetadata $classMetaData */
        $classMetaData = $class['metadata'];
        $classRefl = $classMetaData->getReflectionClass();
        foreach ($classRefl->getProperties() as $prop) {
            /** @var TsVector $annot */
            $annot = $reader->getPropertyAnnotation($prop, TsVector::class);
            if (is_null($annot)) {
                continue;
            }
            if (in_array($this->ftsField->field, $annot->properties)) {
                $this->ftsField->field = $prop->name;
                break;
            }
        }
    }

    protected function resolveFulltextLanguageSql(SqlWalker $sqlWalker): string
    {
        $fulltextVectorMapping = $this->getFulltextVectorMapping($sqlWalker);

        if ($fulltextVectorMapping['languageProperty'] === null) {
            $languageSql = $sqlWalker->walkStringPrimary($fulltextVectorMapping['language']);
        } else {
            $languageExpression = clone $this->ftsField;
            $languageExpression->field = $fulltextVectorMapping['languageProperty'];
            $languageSql = $languageExpression->dispatch($sqlWalker);
        }

        return "{$languageSql}::regconfig";
    }

    protected function getFulltextVectorMapping(SqlWalker $sqlWalker): array
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
