<?php
namespace VertigoLabs\DoctrineFullTextPostgres\ORM\Query\AST\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class TsOrFunction extends FunctionNode implements TsQueryFunctionInterface
{
    /** @var Node */
    private $left;

    /** @var Node */
    private $right;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->left = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->right = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return "({$this->left->dispatch($sqlWalker)}) || ({$this->right->dispatch($sqlWalker)})";
    }
}
