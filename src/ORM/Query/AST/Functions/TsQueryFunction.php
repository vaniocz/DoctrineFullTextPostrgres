<?php
namespace VertigoLabs\DoctrineFullTextPostgres\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\SqlWalker;

class TsQueryFunction extends TSFunction
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        if ($this->queryString instanceof TsQueryFunctionInterface) {
            return "{$this->ftsField->dispatch($sqlWalker)} @@ ({$this->queryString->dispatch($sqlWalker)})";
        }

        return sprintf(
            '%s @@ to_tsquery(%s, %s)',
            $this->ftsField->dispatch($sqlWalker),
            $this->resolveFulltextLanguageSql($sqlWalker),
            $this->queryString->dispatch($sqlWalker)
        );
    }
}
