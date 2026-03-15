<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * Abstract base for all AST (Abstract Syntax Tree) nodes.
 *
 * Each subclass encodes one syntactic construct in the expression grammar.
 * Nodes are produced by {@see \Nalabdou\Algebra\Expression\Parser} and
 * consumed by {@see \Nalabdou\Algebra\Expression\Evaluator}.
 *
 * @internal
 */
abstract class Node
{
}
