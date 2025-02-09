<?php

declare(strict_types=1);

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
class IntegrationTest extends TestCase
{
    private array $dbConfig;

    private Adapter $adapter;

    private string $schemaTable;

    private string $schemaTableQuoted;

    protected function setUp(): void
    {
        if ((bool)getenv('INTEGRATION_ENABLED') !== true) {
            static::markTestSkipped();
            return;
        }

        parent::setUp();

        $this->dbConfig = [
            'host' => getenv('INTEGRATION_HOST'),
            'port' => getenv('INTEGRATION_PORT'),
            'username' => getenv('INTEGRATION_USERNAME'),
            'password' => getenv('INTEGRATION_PASSWORD'),
        ];

        $this->adapter = new Adapter($this->dbConfig);
    }

    protected function tearDown(): void
    {
        if (isset($this->schemaTableQuoted)) {
            $this->adapter->query("DROP TABLE {$this->schemaTableQuoted}");
        }
    }

    public function testPing(): void
    {
        static::assertTrue($this->adapter->ping());
    }

    public function testQuery(): void
    {
        $expected = sha1(uniqid());

        $stmt = $this->adapter->query('SELECT "' . $expected . '"');
        $result = $stmt->fetchColumn();

        static::assertSame($expected, $result);
    }

    public function testRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');
        $this->expectExceptionCode(1045);

        $adapter = new Adapter([
            'host' => getenv('INTEGRATION_HOST'),
            'port' => getenv('INTEGRATION_PORT'),
            'username' => 'invalid_user',
            'password' => 'invalid_pass',
        ]);

        $adapter->getConnection();
    }

    public function testRuntimeExceptionStringCode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Base table or view not found');
        $this->expectExceptionCode('42S02');

        $this->adapter->setDatabase(getenv('INTEGRATION_DATABASE'));
        $this->adapter->query('SELECT foo FROM bar');
    }

    public function testInvalidQueryException(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('error in your SQL syntax');
        $this->expectExceptionCode(42000);

        $this->adapter->query('SELECT foo FROM bar WHERE');
    }

    #[DataProvider('dataSetCharset')]
    public function testSetCharset(string $charset): void
    {
        $config = $this->dbConfig + [
            'charset' => $charset,
        ];

        $adapter = new Adapter($config);

        $variables = $adapter->query('SHOW SESSION VARIABLES LIKE "character_set%"')->fetchAll(\PDO::FETCH_KEY_PAIR);

        self::assertSame($charset, $variables['character_set_client']);
        self::assertSame($charset, $variables['character_set_connection']);
        self::assertSame($charset, $variables['character_set_results']);
    }

    public static function dataSetCharset(): array
    {
        return [
            'utf8mb4' => ['utf8mb4'],
            'latin1' => ['latin1'],
            'ascii' => ['ascii'],
        ];
    }

    #[DataProvider('dataSetTimezone')]
    public function testSetTimezone(string $timezone): void
    {
        $config = $this->dbConfig + [
            'timezone' => $timezone,
        ];

        $adapter = new Adapter($config);

        $variables = $adapter->query('SHOW SESSION VARIABLES LIKE "time_zone"')->fetchAll(\PDO::FETCH_KEY_PAIR);

        self::assertSame($timezone, $variables['time_zone']);
    }

    public static function dataSetTimezone(): array
    {
        return [
            'default' => ['+00:00'],
            'offset-2' => ['+02:00'],
            'named-system' => ['SYSTEM'],
            'named-utc' => ['UTC'],
            'named-london' => ['Europe/London'],
            'named-helsinki' => ['Europe/Helsinki'],
        ];
    }

    public function testSetDatabaseNotConnectedSuccess(): void
    {
        // Connection not active, will just update config
        $this->adapter->setDatabase(getenv('INTEGRATION_DATABASE'));

        // Connection made, no exception for set database
        static::assertTrue($this->adapter->ping());
    }

    public function testSetDatabaseAlreadyConnectedSuccess(): void
    {
        // Make sure connected
        static::assertTrue($this->adapter->ping());

        // No exception should be thrown for valid database
        $this->adapter->setDatabase(getenv('INTEGRATION_DATABASE'));

        // Still connected
        static::assertTrue($this->adapter->ping());
    }

    /**
     * This test needs the user to have global privileges, otherwise the error will be 'access denied'
     */
    public function testSetDatabaseNotConnectedFail(): void
    {
        // Connection not active, will just update config, no exception
        $this->adapter->setDatabase('database_does_not_exist');

        // Trigger connection, exception should be thrown
        $this->expectException(UnknownDatabaseException::class);
        $this->expectExceptionCode(UnknownDatabaseException::ER_BAD_DB_ERROR_1);
        $this->adapter->query('SELECT 1');
    }

    /**
     * This test needs the user to have global privileges, otherwise the error will be 'access denied'
     */
    public function testSetDatabaseAlreadyConnectedFail(): void
    {
        // Make sure connected
        static::assertTrue($this->adapter->ping());

        $this->expectException(UnknownDatabaseException::class);
        $this->expectExceptionCode(UnknownDatabaseException::ER_BAD_DB_ERROR_1);
        $this->adapter->setDatabase('database_does_not_exist');
    }

    public function testBasicDataManip(): void
    {
        $this->createTestTable();
        $id = rand();
        $text = sha1(uniqid());

        $insertSql = <<<SQL
INSERT INTO {$this->schemaTableQuoted} (
    test_id, char_col
) VALUES (
    {$id}, "{$text}"
)
SQL;
        $insertCount = $this->adapter->execute($insertSql);
        static::assertSame(1, $insertCount);

        $selectSql = <<<SQL
SELECT char_col
FROM {$this->schemaTableQuoted}
WHERE test_id = {$id}
SQL;
        $stmt = $this->adapter->query($selectSql);
        static::assertSame(1, $stmt->rowCount());
        static::assertSame($text, $stmt->fetchColumn());

        $deleteSql = <<<SQL
DELETE FROM {$this->schemaTableQuoted}
WHERE test_id = {$id}
SQL;
        $deleteCount = $this->adapter->execute($deleteSql);
        static::assertSame(1, $deleteCount);
    }

    public function testCrudMethods(): void
    {
        $this->createTestTable();
        $id = rand();
        $text1 = sha1(uniqid());
        $text2 = sha1(uniqid());

        $insertData = [
            'test_id' => $id,
            'char_col' => $text1,
        ];
        $insertCount = $this->adapter->insert($this->schemaTable, $insertData);
        static::assertSame(1, $insertCount);

        $selectWhere = [
            'test_id' => $id,
        ];
        $selectStmt1 = $this->adapter->select($this->schemaTable, $selectWhere);
        static::assertSame(1, $selectStmt1->rowCount());
        $selectActual1 = $selectStmt1->fetch();
        static::assertSame($text1, $selectActual1['char_col']);

        $updateData = [
            'char_col' => $text2,
        ];
        $updateCount = $this->adapter->update($this->schemaTable, $updateData, $selectWhere);
        static::assertSame(1, $updateCount);

        $selectStmt2 = $this->adapter->select($this->schemaTable, $selectWhere);
        static::assertSame(1, $selectStmt2->rowCount());
        $selectActual2 = $selectStmt2->fetch();
        static::assertSame($text2, $selectActual2['char_col']);

        $deleteWhere = [
            'test_id' => $id,
        ];
        $deleteCount = $this->adapter->delete($this->schemaTable, $deleteWhere);
        static::assertSame(1, $deleteCount);
    }

    public function testLastInsertId(): void
    {
        $this->createTestTable();
        $text1 = sha1(uniqid());
        $text2 = sha1(uniqid());

        $insertSql1 = <<<SQL
INSERT INTO {$this->schemaTableQuoted} (
    char_col
) VALUES (
    "{$text1}"
)
SQL;
        $insertCount1 = $this->adapter->execute($insertSql1);
        static::assertSame(1, $insertCount1);
        static::assertSame('1', $this->adapter->lastInsertId());

        $insertSql2 = <<<SQL
INSERT INTO {$this->schemaTableQuoted} (
    char_col
) VALUES (
    "{$text2}"
)
SQL;
        $insertCount2 = $this->adapter->execute($insertSql2);
        static::assertSame(1, $insertCount2);
        static::assertSame('2', $this->adapter->lastInsertId());
    }

    private function createTestTable(): void
    {
        $tableName = 'phlib_db_test_' . substr(sha1(uniqid()), 0, 10);
        $this->schemaTable = getenv('INTEGRATION_DATABASE') . '.' . $tableName;
        $this->schemaTableQuoted = '`' . getenv('INTEGRATION_DATABASE') . "`.`{$tableName}`";

        $sql = <<<SQL
CREATE TABLE {$this->schemaTableQuoted} (
  `test_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `char_col` varchar(255) DEFAULT NULL,
  `update_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii
SQL;

        $this->adapter->query($sql);
    }
}
