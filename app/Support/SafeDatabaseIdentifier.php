<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SafeDatabaseIdentifier
{
    private const IDENTIFIER_PATTERN =
        '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*\z/D';

    /**
     * Validate and quote an internal table or column identifier.
     *
     * SQL parameter bindings cannot bind table or column names. Any identifier
     * used inside a raw SQL fragment must therefore be constrained before it is
     * quoted by the active database grammar.
     */
    public static function wrap(
        string $identifier
    ): string {
        $identifier = trim(
            $identifier
        );

        if (
            $identifier === ''
            || preg_match(
                self::IDENTIFIER_PATTERN,
                $identifier
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Unsafe database identifier rejected.'
            );
        }

        return DB::connection()
            ->getQueryGrammar()
            ->wrap($identifier);
    }
}
