<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLite;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

/**
 * @extends AbstractDriverTest<SqlitePlatform>
 */
class AbstractSQLiteDriverTest extends AbstractDriverTest
{
    protected function createDriver(): Driver
    {
        return $this->getMockForAbstractClass(AbstractSQLiteDriver::class);
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new SqlitePlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new SqliteSchemaManager(
            $connection,
            $this->createPlatform()
        );
    }

    protected function createExceptionConverter(): ExceptionConverter
    {
        return new SQLite\ExceptionConverter();
    }

    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion(): void
    {
        self::markTestSkipped('SQLite drivers do not use server version to instantiate platform');
    }

    /**
     * {@inheritDoc}
     */
    public static function platformVersionProvider(): array
    {
        self::markTestSkipped('SQLite drivers use one platform implementation for all server versions');
    }
}