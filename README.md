# phlib/db

MySQL PDO DB Adapter. PDO with some extra good stuff specifically for MySQL.

## Install

Via Composer

``` bash
$ composer require phlib/db
```

## Usage

@todo

## Exception Hierarchy

All Phlib Db Exceptions implement the ```\Phlib\Db\Exception\Exception``` interface.

```php
try {
    $db = new \Phlib\Db\Adapter($config);
    $result = $db->query($sql, $bind);
} catch (\Phlib\Db\Exception\Exception $e) {
    $this->logException($e);
}
```

<pre>
+-- \Exception
|  +-- \InvalidArgumentException
|  |  +-- \Phlib\Db\Exception\InvalidArgumentException
|  +-- \RuntimeException
|  |  +-- \PDOException
|  |  |  +-- \Phlib\Db\Exception\RuntimeException
|  |  |  |  +-- \Phlib\Db\Exception\UnknownDatabaseException
|  |  |  |  +-- \Phlib\Db\Exception\InvalidQueryException
</pre>
