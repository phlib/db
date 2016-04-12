<?php

namespace Phlib\Db;

use Phlib\Db\Adapter\QuotableAdapterInterface;
use Phlib\Db\Exception\RuntimeException;

/**
 * Class BulkInsert
 *
 * Used to insert large amounts of data into a single table in defined batch
 * sizes.
 *
 * @package Phlib\Db
 */
class BulkInsert
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $insertFields;

    /**
     * @var array
     */
    protected $updateFields;

    /**
     * @var bool
     */
    protected $insertIgnore = false;

    /**
     * @var array
     */
    protected $rows = [];

    /**
     * @var integer
     */
    protected $batchSize;

    /**
     * @var integer
     */
    protected $totalRows = 0;

    /**
     * @var integer
     */
    protected $totalInserted = 0;

    /**
     * @var integer
     */
    protected $totalUpdated = 0;

    /**
     * Constructor
     *
     * @param Adapter $adapter
     * @param string  $table
     * @param array   $insertFields
     * @param array   $updateFields
     * @param array   $options int batchSize = 200
     */
    public function __construct(QuotableAdapterInterface $adapter, $table, array $insertFields, array $updateFields = [], array $options = [])
    {
        $options = $options + ['batchSize' => 200];

        $this->adapter   = $adapter;
        $this->table     = $table;
        $this->batchSize = (integer)$options['batchSize'];

        $this->setInsertFields($insertFields);
        $this->setUpdateFields($updateFields);
    }

    /**
     * Sets the insert fields for the bulk statement.
     *
     * @param  array $fields
     * @return $this
     */
    public function setInsertFields(array $fields)
    {
        $this->insertFields = $fields;
        return $this;
    }

    /**
     * Sets the update fields for the bulk statement.
     *
     * @param  array $fields
     * @return $this
     */
    public function setUpdateFields(array $fields)
    {
        $this->updateFields = [];
        if (count($fields) > 0) {
            $values = [];
            foreach($fields as $key => $value) {
                if (is_int($key)) {
                    $values[] = "$value = VALUES($value)";
                } else {
                    $values[] = $this->adapter->quoteInto("$key = ?", $value);
                }
            }
            $this->updateFields = $values;
        }

        return $this;
    }

    /**
     * Adds a row to the bulk insert. Row should be an indexed array matching
     * the order of the fields given. If the magic number is reached then it'll
     * automatically write the changes to the database.
     *
     * @param  array $row
     * @return $this
     */
    public function add(array $row)
    {
        if (count($row) == count($this->insertFields)) {
            $this->rows[] = $row;
            if (count($this->rows) >= $this->batchSize) {
                $this->write();
            }
        }
        return $this;
    }

    /**
     * Writes the changes so far to the database.
     *
     * @return $this
     * @throws RuntimeException
     */
    public function write()
    {
        $rowCount = count($this->rows);
        if ($rowCount == 0) {
            return $this;
        }

        $sql = $this->fetchSql();
        do {
            try {
                $affectedRows = $this->adapter->execute($sql);
            } catch (RuntimeException $e) {
                if (stripos($e->getMessage(), 'Deadlock') === false) {
                    throw $e;
                }
                $affectedRows = false;
            }
        } while($affectedRows === false);

        $this->rows = [];

        $updatedRows          = $affectedRows - $rowCount;
        $this->totalRows     += $rowCount;
        $this->totalInserted += $rowCount - $updatedRows;
        $this->totalUpdated  += $updatedRows;

        return $this;
    }

    /**
     * Constructs the bulk insert statement based on the rows added so far. If
     * no rows have been added then it returns false.
     *
     * @return string|false
     */
    public function fetchSql()
    {
        if (count($this->rows) == 0) {
            return false;
        }
        $values = [];
        foreach($this->rows as $row) {
            array_map([$this->adapter, 'quote'], $row);
            $values[] = '(' . implode(', ', $row) . ')';
        }
        $values = implode(', ', $values);

        // Build statement structure
        $insert = ['INSERT'];
        $update = '';
        if (!empty($this->updateFields)) {
            $update = 'ON DUPLICATE KEY UPDATE ' . implode(', ', $this->updateFields);
        } elseif ($this->insertIgnore === true) {
            $insert[] = 'IGNORE';
        }
        $insert[] = "INTO {$this->table}";
        $insert[] = "(" . implode(', ', $this->insertFields) . ") VALUES";

        return trim(implode(' ', $insert) . " $values $update");
    }

    /**
     * Gets statistics about bulk insert. If flush is true then it will clear
     * any rows still outstanding before returning the results.
     *
     * Return:
     * array(
     *     'total'    => 100,
     *     'inserted' => 50,
     *     'updated'  => 50,
     *     'pending'  => 0
     * )
     *
     * @param  boolean $flush
     * @return array
     */
    public function fetchStats($flush = true)
    {
        if ((boolean)$flush) {
            $this->write();
        }
        $stats = [
            'total'    => $this->totalRows,
            'inserted' => $this->totalInserted,
            'updated'  => $this->totalUpdated,
            'pending'  => count($this->rows)
        ];
        return $stats;
    }

    /**
     * Clear the currently recorded statistics.
     *
     * @return $this
     */
    public function clearStats()
    {
        $this->totalRows     = 0;
        $this->totalInserted = 0;
        $this->totalUpdated  = 0;
        return $this;
    }

    /**
     * Enable usage of INSERT INGORE
     *
     * @return $this
     */
    public function insertIgnoreEnabled()
    {
        $this->insertIgnore = true;
        return $this;
    }

    /**
     * Disable usage of INSERT INGORE
     *
     * @return $this
     */
    public function insertIgnoreDisabled()
    {
        $this->insertIgnore = false;
        return $this;
    }
}
