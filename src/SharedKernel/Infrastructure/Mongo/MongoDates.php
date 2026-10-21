<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Mongo;

use MongoDB\BSON\UTCDateTime;

/**
 * BSON dates have millisecond precision and no time zone: values are stored in UTC
 * and read back as UTC.
 */
final class MongoDates
{
    public static function toBson(\DateTimeImmutable $date): UTCDateTime
    {
        return new UTCDateTime($date);
    }

    public static function fromBson(UTCDateTime $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date->toDateTime())
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function fromNullableBson(?UTCDateTime $date): ?\DateTimeImmutable
    {
        return $date === null ? null : self::fromBson($date);
    }
}
