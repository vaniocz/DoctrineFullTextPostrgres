<?php
namespace VertigoLabs\DoctrineFullTextPostgres\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\SqlWalker;

class PlainTsRankFunction extends TSFunction
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'ts_rank(%s, plainto_tsquery(%s, %s))',
            $this->ftsField->dispatch($sqlWalker),
            $this->resolveFulltextLanguageSql($sqlWalker),
            $this->queryString->dispatch($sqlWalker)
        );
    }
}
