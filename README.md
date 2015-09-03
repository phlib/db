# phlib/db

MySQL PDO DB Adapter. PDO with some extra good stuff specifically for MySQL.

What this DB wrapper offers over others.
* Database switching method
* Unified unknown database exception
* Capturing invalid SQL statements exception
* Automatic reconnect useful for long running processes (server has gone away)
* Connection retries on failed to connect
* Connection cloning
* Useful additional methods for:
  * timezone
  * buffering
  * quoting (tables, columns, values)
  * select
  * insert
  * update
  * delete

## Install

Via Composer

``` bash
$ composer require phlib/db
```
or
``` JSON
"require": {
    "phlib/db": "*"
}
```

## Basic Usage

``` php
$config = [
    'host' => 'localhost',
    'username' => 'myuser',
    'password' => 'mypassword',
    'dbname' => 'mydatabase'
];
$db = new \Phlib\Db\Adapter($config);
```

``` php
$table = $db->quoteIdentifier('mytable');
/* @var $stmt \PDOStatement */
$stmt = $db->query("SELECT * FROM $table WHERE id = ?", [$rowId]);
```

### Odd Cases

Setting the connection from outside the class will cause odd behaviour.

``` php
$pdo = new \PDO('mysql:host=localhost');
$db = new \Phlib\Db\Adapter();
$db->setConnection($pdo);
$config = $db->getConfig(); // config is an empty array

$db->reconnect(); // throws InvalidArgumentException missing host param.
```

## Configuration

|Name|Type|Required|Default|Description|
|----|----|--------|-------|-----------|
|`host`|*String*|Yes| |Hostname or IP address.|
|`username`|*String*|No|`''`|Username to connect to server.|
|`password`|*String*|No|`''`|Password to connect to server.|
|`port`|*Integer*|No| |Port to connect to server.|
|`dbname`|*String*|No| |Database name to use.|
|`charset`|*String*|No|`'utf8mb4'`|Sets the character to use on the connection.|
|`timezone`|*String*|No|`'+0:00'`|Sets the timezone to use on the connection.|
|`timeout`|*Integer*|No|`2`|Sets the connection timeout. Range from 0 to 120.|
|`retryCount`|*Integer*|No|`0`|Sets how many times to try to reconnect to the DB server after unsuccessful connection attempts. Range from 0 to 10.|


## API

The following section documents the less obvious API's. Most methods are doc blocked and are self explanatory.

`Adapter::__clone`
This is useful when you're dealing with the results of one query while inserting as both operations can not be done
on the same connection.
``` php
$db2 = (clone)$db1;
```

`Adapter` Buffering
This is useful when requesting large amounts of data from the DB server. By default, PDO will pull all the results
back and hold the results in memory even for `fetch()` calls. With large result sets this causes out of memory problems.
To stop PDO pulling the results back turn off buffering.
``` php
$db->setBuffered($enabled = false);
```

## Exceptions

All Phlib Db Exceptions implement the ```\Phlib\Db\Exception\Exception``` interface.

``` php
try {
    $db = new \Phlib\Db\Adapter($config);
    $result = $db->query($sql, $bind);
} catch (\Phlib\Db\Exception\Exception $e) {
    $this->logException($e);
}
```

### Hierarchy
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
