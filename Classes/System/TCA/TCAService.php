<?php

namespace ApacheSolrForTypo3\Solr\System\TCA;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 Timo Hund <timo.hund@dkd.de
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class to encapsulate TCA specific logic
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TCAService
{
    /**
     * @var array
     */
    protected $tca = [];

    /**
     * @var array
     */
    protected $visibilityAffectingFields = [];

    /**
     * TCAService constructor.
     * @param array|null $TCA
     */
    public function __construct($TCA = null)
    {
        $this->tca = (array)($TCA ?? $GLOBALS['TCA']);
    }

    /**
     * @return integer
     */
    protected function getTime()
    {
        return isset($GLOBALS['EXEC_TIME']) ? $GLOBALS['EXEC_TIME'] : time();
    }

    /**
     * Checks if a record is "enabled"
     *
     * A record is considered "enabled" if
     *  - it is not hidden
     *  - it is not deleted
     *  - as a page it is not set to be excluded from search
     *
     * @param string $table The record's table name
     * @param array $record The record to check
     * @return bool TRUE if the record is enabled, FALSE otherwise
     */
    public function isEnabledRecord($table, $record)
    {
        if (
            (empty($record))
            ||
            (isset($this->tca[$table]['ctrl']['enablecolumns']['disabled']) && !empty($record[$this->tca[$table]['ctrl']['enablecolumns']['disabled']]))
            ||
            (isset($this->tca[$table]['ctrl']['delete']) && !empty($record[$this->tca[$table]['ctrl']['delete']]))
            ||
            ($table === 'pages' && !empty($record['no_search']))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether a end time field exists for the record's table and if so
     * determines if a time is set and whether that time is in the past,
     * making the record invisible on the website.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return bool True if the record's end time is in the past, FALSE otherwise.
     */
    public function isEndTimeInPast($table, $record)
    {
        $endTimeInPast = false;

        if (isset($this->tca[$table]['ctrl']['enablecolumns']['endtime'])) {
            $endTimeField = $this->tca[$table]['ctrl']['enablecolumns']['endtime'];
            if ($record[$endTimeField] > 0) {
                $endTimeInPast = $record[$endTimeField] < $this->getTime();
            }
        }

        return $endTimeInPast;
    }

    /**
     * This method can be used to check if there is a configured key in
     *
     * $GLOBALS['TCA']['mytable']['ctrl']['enablecolumns']
     *
     * Example:
     *
     * $GLOBALS['TCA']['mytable']]['ctrl']['enablecolumns']['fe_group'] = 'mygroupfield'
     *
     * ->isEnableColumn('mytable', 'fe_group') will return true, because 'mygroupfield' is
     * configured as column.
     *
     * @params string $table
     * @param string $columnName
     * @return bool
     */
    public function isEnableColumn($table, $columnName)
    {
        return (
            isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']) &&
            array_key_exists($columnName, $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])
        );
    }

    /**
     * Checks whether a start time field exists for the record's table and if so
     * determines if a time is set and whether that time is in the future,
     * making the record invisible on the website.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return bool True if the record's start time is in the future, FALSE otherwise.
     */
    public function isStartTimeInFuture($table, $record)
    {
        $startTimeInFuture = false;

        if (isset($this->tca[$table]['ctrl']['enablecolumns']['starttime'])) {
            $startTimeField = $this->tca[$table]['ctrl']['enablecolumns']['starttime'];
            $startTimeInFuture = $record[$startTimeField] > $this->getTime();
        }

        return $startTimeInFuture;
    }


    /**
     * Checks whether a hidden field exists for the current table and if so
     * determines whether it is set on the current record.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return bool True if the record is hidden, FALSE otherwise.
     */
    public function isHidden($table, $record)
    {
        $hidden = false;

        if (isset($this->tca[$table]['ctrl']['enablecolumns']['disabled'])) {
            $hiddenField = $this->tca[$table]['ctrl']['enablecolumns']['disabled'];
            $hidden = (boolean)$record[$hiddenField];
        }

        return $hidden;
    }

    /**
     * Makes sure that "empty" frontend group fields are always the same value.
     *
     * @param string $table The record's table name.
     * @param array $record the record array.
     * @return array The cleaned record
     */
    public function normalizeFrontendGroupField($table, $record)
    {
        if (isset($this->tca[$table]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroupsField = $this->tca[$table]['ctrl']['enablecolumns']['fe_group'];

            if ($record[$frontendGroupsField] == '') {
                $record[$frontendGroupsField] = '0';
            }
        }

        return $record;
    }

    /**
     * @param string $table
     * @param array $record
     * @return mixed
     */
    public function getTranslationOriginalUid($table, array $record)
    {
        return $record[$this->tca[$table]['ctrl']['transOrigPointerField']];
    }

    /**
     * Retrieves the uid that as marked as original if the record is a translation if not it returns the
     * originalUid.
     *
     * @param $table
     * @param array $record
     * @param $originalUid
     * @return integer
     */
    public function getTranslationOriginalUidIfTranslated($table, array $record, $originalUid)
    {
        if (!$this->isLocalizedRecord($table, $record)) {
            return $originalUid;
        }

        return $this->getTranslationOriginalUid($table, $record);
    }

    /**
     * Checks whether a record is a localization overlay.
     *
     * @param string $tableName The record's table name
     * @param array $record The record to check
     * @return bool TRUE if the record is a language overlay, FALSE otherwise
     */
    public function isLocalizedRecord($tableName, array $record)
    {
        $translationUid = $this->getTranslationOriginalUid($tableName, $record);
        if (is_null($translationUid)) {
            return false;
        }

        $hasTranslationReference = $translationUid > 0;
        if (!$hasTranslationReference) {
            return false;
        }

        return true;
    }

    /**
     * Compiles a list of visibility affecting fields of a table so that it can
     * be used in SQL queries.
     *
     * @param string $table Table name to retrieve visibility affecting fields for
     * @return string Comma separated list of field names that affect the visibility of a record on the website
     */
    public function getVisibilityAffectingFieldsByTable($table)
    {
        if (isset($this->visibilityAffectingFields[$table])) {
            return $this->visibilityAffectingFields[$table];
        }

        // we always want to get the uid and pid although they do not affect visibility
        $fields = ['uid', 'pid'];
        if (isset($this->tca[$table]['ctrl']['enablecolumns'])) {
            $fields = array_merge($fields, $this->tca[$table]['ctrl']['enablecolumns']);
        }

        if (isset($this->tca[$table]['ctrl']['delete'])) {
            $fields[] = $this->tca[$table]['ctrl']['delete'];
        }

        if ($table === 'pages') {
            $fields[] = 'no_search';
            $fields[] = 'doktype';
        }

        $this->visibilityAffectingFields[$table] = implode(', ', $fields);

        return $this->visibilityAffectingFields[$table];
    }

    /**
     * Checks if TCA is available for column by table
     *
     * @param string $tableName
     * @param string $fieldName
     * @return bool
     */
    public function getHasConfigurationForField(string $tableName, string $fieldName) : bool
    {
        return isset($this->tca[$tableName]['columns'][$fieldName]);
    }

    /**
     * Returns the tca configuration for a certains field
     *
     * @param string $tableName
     * @param string $fieldName
     * @return array
     */
    public function getConfigurationForField(string $tableName, string $fieldName) : array
    {
        return $this->tca[$tableName]['columns'][$fieldName] ?? [];
    }

    /**
     * @param string $tableName
     * @return array
     */
    public function getTableConfiguration(string $tableName) : array
    {
        return $this->tca[$tableName] ?? [];
    }
}
