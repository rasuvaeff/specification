<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification;

/**
 * How a LIKE value is turned into the SQL pattern.
 *
 * `Pattern` sends the value verbatim: the caller has written the wildcards.
 * The other three hand a clean substring to the query builder, which escapes
 * `%`, `_` and `\` in it and adds the wildcard itself.
 *
 * @api
 */
enum LikeMatch
{
    case Pattern;
    case StartsWith;
    case EndsWith;
    case Contains;
}
