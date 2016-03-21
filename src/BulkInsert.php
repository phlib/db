<?php

namespace Phlib\Db\Helper;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\RuntimeException;

/**
 * Class BulkInsert
 *
 * Used to insert large amounts of data into a single table in defined batch
 * sizes.
 *
 * @package Phlib\Db\Helper
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
    protected $rows = array();

    /**
     * @var integer
     */
    protected $bulkSize;

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
     * @param integer $bulkSize
     */
    public function __construct(Adapter $adapter, $table, array $insertFields, array $updateFields = array(), $bulkSize = 200)
    {
        $this->adapter  = $adapter;
        $this->table    = $table;
        $this->bulkSize = (integer)$bulkSize;

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
        $this->updateFields = array();
        if (count($fields) > 0) {
            $values = array();
            foreach($fields as $key => $value) {
                if (is_int($key)) {
                    $values[] = "$value = VALUES($value)";
                } else {
                    $this->adapter->quoteByRef($value);
                    $values[] = "$key = $value";
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
            if (count($this->rows) >= $this->bulkSize) {
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
        if ($rowCount > 0) {
            $sql = $this->fetchSql();

            do {
                try {
                    $affectedRows = $this->adapter->exec($sql);
                } catch (RuntimeException $e) {
                    if (stripos($e->getMessage(), 'Deadlock') === false) {
                        throw $e;
                    }
                    $affectedRows = false;
                }
            } while($affectedRows === false);

            $this->rows  = array();

            $updatedRows          = $affectedRows - $rowCount;
            $this->totalRows     += $rowCount;
            $this->totalInserted += $rowCount - $updatedRows;
            $this->totalUpdated  += $updatedRows;
        }
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
        $values = array();
        foreach($this->rows as $row) {
            array_walk($row, array($this->adapter, 'quoteByRef'));
            $values[] = '(' . implode(', ', $row) . ')';
        }
        $values = implode(', ', $values);

        // Build statement structure
        $insert = array('INSERT');
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
     * @param  boolean $clearStats
     * @return array
     */
    public function fetchStats($flush = true, $clearStats = true)
    {
        if ((boolean)$flush) {
            $this->write();
        }
        $stats = array(
            'total'    => $this->totalRows,
            'inserted' => $this->totalInserted,
            'updated'  => $this->totalUpdated,
            'pending'  => count($this->rows)
        );
        if ((boolean)$clearStats) {
            $this->totalRows = 0;
            $this->totalInserted = 0;
            $this->totalUpdated = 0;
        }
        return $stats;
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