<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub\EvalDeclared;

/**
 * Its parent is declared by eval() in the test, so it has no source file
 */
class ChildOfEvalDeclaredParent extends EvalDeclaredParent
{
    public function ownMethod(): void
    {
    }
}
