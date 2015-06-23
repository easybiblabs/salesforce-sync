<?php
/**
 * Copyright (c) 2015 Imagine Easy Solutions LLC
 * MIT licensed, see LICENSE file for details.
 */
namespace EasyBib\Silex\Salesforce;

use SforcePartnerClient;
use SObject;

class Service
{
    /** @var SforcePartnerClient */
    private $client;
    /** @var array */
    private $fieldMap;
    /** @var string */
    private $filterClause;
    /** @var callable */
    private $upsertFunction;

    /**
     * @param \SforcePartnerClient $client - a logged-in salesforce client
     * @param array $fieldMap - a map from salesforce field name to your own field name
     * @param string $filterClause - a WHERE/HAVING/LIMIT statement for the salesforce API
     * @param callable $upsertFunction - a function (array $records) => number of updated records
     *        to do the insert/update in you local DB.
     *        Will be called with an array of hash-maps (arrays), with the fields from your fieldmap.
     *        This function may get called more than once (once per batch).
     *        count($records) will always be more than 0 and less than 2001.
     */
    public function __construct(SforcePartnerClient $client, array $fieldMap, $filterClause, callable $upsertFunction)
    {
        $this->client = $client;
        $this->fieldMap = $fieldMap;
        $this->filterClause = $filterClause;
        $this->upsertFunction = $upsertFunction;
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
        $data = $this->query($query);
        $upsert = $this->upsertFunction;
        return [count($data), $upsert($data)];
    }

    private function query($query)
    {
        $response = $this->client->query($query);
        return array_map(
            function ($record) {
                $sobj = new SObject($record);
                $data = $sobj->fields;
                $data->Id = $sobj->Id;
                $out = [];
                foreach ($data as $key => $value) {
                    if (!$value instanceof SObject) {
                        if (isset($this->fieldMap[$key])) {
                            $out[$this->fieldMap[$key]] = $value;
                        }
                        continue;
                    }
                    // joined fields (Owner.Name, Owner.Email)
                    foreach ($value->fields as $subkey => $subvalue) {
                        $complexKey = $key . '.' . $subkey;
                        if (isset($this->fieldMap[$complexKey])) {
                            $out[$this->fieldMap[$complexKey]] = $subvalue;
                        }
                    }
                }
                return $out;
            },
            $response->records
        );
    }

    private function createSalesforceQuery(array $fieldMap, $filterClause)
    {
        $fieldList = implode(',', array_keys($fieldMap));
        $query = "SELECT $fieldList FROM Account $filterClause";
        return $query;
    }
}
