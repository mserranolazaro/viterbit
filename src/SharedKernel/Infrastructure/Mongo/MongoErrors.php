<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Mongo;

use MongoDB\Driver\Exception\BulkWriteException;

final class MongoErrors
{
    private const int DUPLICATE_KEY = 11000;

    public static function isDuplicateKey(BulkWriteException $e): bool
    {
        foreach ($e->getWriteResult()->getWriteErrors() as $error) {
            if ($error->getCode() === self::DUPLICATE_KEY) {
                return true;
            }
        }

        return false;
    }
}
