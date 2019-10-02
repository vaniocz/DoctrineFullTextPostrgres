<?php
namespace VertigoLabs\DoctrineFullTextPostgres\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class PlainToTsQueryFunction extends FunctionNode implements TsQueryFunctionInterface
{
    /** @var Node */
    private $language;

    /** @var Node */
    private $term;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->language = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->term = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'plainto_tsquery((%s)::regconfig, %s)',
            $this->language->dispatch($sqlWalker),
            $this->term->dispatch($sqlWalker)
        );
    }
}
