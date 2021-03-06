<?php
/**
 * Copyright (c) 2016 Chegg Inc.
 * Apache-2.0 licensed, see LICENSE.txt file for details.
 *
 * @license  Apache-2.0
 * @link     http://www.apache.org/licenses/LICENSE-2.0
 */
namespace EasyBib\Silex\Salesforce;

use QueryResult;
use SObject;

class Service
{
    /** @var ClientProxy */
    private $client;
    /** @var array */
    private $fieldMap;
    /** @var string */
    private $filterClause;
    /** @var callable */
    private $upsertFunction;
    /** @var callable */
    private $cleanupFunction;

    /**
     * @param ClientProxy $client
     * @param array $fieldMap - a map from salesforce field name to your own field name
     * @param string $filterClause - a WHERE/HAVING/LIMIT statement for the salesforce API
     * @param callable $upsertFunction - a function (array $records) => number of updated records
     *        to do the insert/update in your local DB.
     *        Will be called with an array of hash-maps (arrays), with the fields from your fieldmap.
     *        This function may get called more than once (once per batch).
     *        count($records) will always be more than 0 and less than 2001.
     * @param callable $cleanupFunction (optional) - a function (array $ids) => number of updated records
     *        that recieves all ids of the records we got from salesforce.
     */
    public function __construct(
        ClientProxy $client,
        array $fieldMap,
        $filterClause,
        callable $upsertFunction,
        callable $cleanupFunction = null)
    {
        $this->client = $client;
        $this->fieldMap = $fieldMap;
        $this->filterClause = $filterClause;
        $this->upsertFunction = $upsertFunction;
        $this->cleanupFunction = $cleanupFunction;
    }

    /**
     * Sync from salesforce to local DB.
     * @param string $accountId (optional, if given sync only that one account)
     * @return array [size of salesforce response, number of (local) affected records]
     */
    public function sync($accountId = null)
    {
        $query = $this->createSalesforceQuery($this->fieldMap, $this->filterClause);
        if ($accountId !== null) {
            $query = $this->createSalesforceQuery($this->fieldMap, 'WHERE Id = \'' . $accountId . '\'');
        }
        $upsert = $this->upsertFunction;

        $totalReceived = 0;
        $totalUpdated = 0;
        $ids = [];
        foreach ($this->query($query) as $data) {
            if ($this->cleanupFunction) {
                $ids = array_merge($ids, array_map(function($r) { return $r['id'];}, $data));
            }
            $totalReceived += count($data);
            $totalUpdated += $upsert($data);
        }
        if ($this->cleanupFunction && $accountId === null && count($ids) > 1) {
            $cleanup = $this->cleanupFunction;
            $cleanup($ids);
        }
        return [$totalReceived, $totalUpdated];
    }

    /**
     * Fetch one account from salesforce.
     * @param string $accountId
     * @return mixed[]|null The salesforce record
     */
    public function fetch($accountId)
    {
        $query = $this->createSalesforceQuery($this->fieldMap, 'WHERE Id = \'' . $accountId . '\'');

        foreach ($this->query($query) as $data) {
            if (empty($data)) {
                return null;
            }

            return $data[0];
        }

        return null;
    }

    private function query($query)
    {
        $response = $this->client->query($query);
        $queryResult = new QueryResult($response);
        $done = false;

        while (!$done) {
            yield $this->formatRecords($queryResult->records);
            if (!$queryResult->done) {
                $response = $this->client->queryMore($queryResult->queryLocator);
                $queryResult = new QueryResult($response);
            } else {
                $done = true;
            }
        }
    }

    private function formatRecords(array $records)
    {
        return array_map(
            function ($record) {
                $sobj = new SObject($record);
                $data = $sobj->fields;
                $data->Id = $sobj->Id;
                $out = [];

                foreach($this->fieldMap as $salesForceField => $mapField)     {

                    $fieldParts = explode('.', $salesForceField);

                    // check field exists
                    if (!$data->{$fieldParts[0]}) {
                        $out[$mapField] = NULL;
                        continue;
                    }


                    if ($data->{$fieldParts[0]} instanceof SObject) {
                        // joined fields (Owner.Name, Owner.Email)
                        $key = $fieldParts[0];
                        foreach ($data->{$key}->fields as $subkey => $subvalue) {
                            $complexKey = $key . '.' . $subkey;
                            if (isset($this->fieldMap[$complexKey])) {
                                $out[$this->fieldMap[$complexKey]] = $subvalue;
                            }
                        }
                    } else {
                        $out[$mapField] = $data->{$fieldParts[0]};
                    }
                }
                return $out;
            },
            $records
        );
    }

    private function createSalesforceQuery(array $fieldMap, $filterClause)
    {
        $fieldList = implode(',', array_keys($fieldMap));
        $query = "SELECT $fieldList FROM Account $filterClause";
        return $query;
    }
}
