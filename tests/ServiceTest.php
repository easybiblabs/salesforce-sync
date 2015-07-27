<?php
/**
 * Copyright (c) 2015 Imagine Easy Solutions LLC
 * MIT licensed, see LICENSE file for details.
 */
namespace EasyBib\Test\Silex\Salesforce;
use EasyBib\Silex\Salesforce;
use EasyBib\Silex\Salesforce\ClientProxy;

class ServiceTest extends \PHPUnit_Framework_TestCase
{
    private $fieldmap;

    public function setUp()
    {
        $this->fieldmap = [
            'Id' => 'id',
            'Owner.Name' => 'ownerName',
            'Custom_Field__c' => 'customField',
            'Custom_Empty_Field__c' => 'customEmptyField',
        ];
    }

    public function testBasicOperation()
    {
        $expected = [
            [
                'id' => '00XXXXXX0XXXXXXXXX',
                'customEmptyField' => null,
                'customField' => 'custom field value',
                'ownerName' => 'Name of the Owner',
            ],
        ];

        $service = new Salesforce\Service(
            $this->mockClientForSingleQuery(),
            $this->fieldmap,
            '',
            function (array $records) use ($expected) {
                $this->assertEquals(1, count($records));
                $this->assertEquals($expected, $records);
                return 1234;
            }
        );

        list($salesforceResults, $updatedRecords) = $service->sync();
        $this->assertEquals(1, $salesforceResults);
        $this->assertEquals(1234, $updatedRecords);
    }

    public function testQueryMore()
    {
        $expected1 = [
            [
                'id' => '00XXXXXX0XXXXXXXXX',
                'customEmptyField' => null,
                'customField' => 'custom field value',
                'ownerName' => 'Name of the Owner',
            ],
        ];
        $expected2 = [
            [
                'id' => '2 00XXXXXX0XXXXXXXXX',
                'customEmptyField' => null,
                'customField' => '2 custom field value',
                'ownerName' => '2 Name of the Owner',
            ],
        ];
        $iteration = 1;

        $service = new Salesforce\Service(
            $this->mockClientForQueryMore(),
            $this->fieldmap,
            '',
            function (array $records) use ($expected1, $expected2, &$iteration) {
                $this->assertEquals(1, count($records));
                if ($iteration == 1) {
                    $this->assertEquals($expected1, $records, 'first batch');
                } else {
                    $this->assertEquals($expected2, $records, 'second batch');
                }
                $iteration += 1;
                return 42;
            }
        );

        list($salesforceResults, $updatedRecords) = $service->sync();
        $this->assertEquals(2, $salesforceResults);
        $this->assertEquals(84, $updatedRecords);
    }


    private function mockClientForSingleQuery()
    {
        // modelled after the real thing
        $sfresponse = (object)[
            'queryLocator' => 'foo',
            'done' => true,
            'size' => 1,
            'records' => [
                (object)[
                    'type' => 'Account',
                    'Id' => ['00XXXXXX0XXXXXXXXX', '00XXXXXX0XXXXXXXXX'],
                    'any' => [
                        '<sf:Custom_Empty_Field__c xsi:nil="true"/><sf:Custom_Field__c>custom field value</sf:Custom_Field__c>',
                        'Owner' => (object)[
                            'type' => 'User',
                            'Id' => null,
                            'any' => '<sf:Name>Name of the Owner</sf:Name><sf:Email>someone@example.com</sf:Email>"',
                        ],
                    ],
                ],
            ],
        ];

        $sfclient = $this->getMockBuilder('SforcePartnerClient')->disableOriginalConstructor()->getMock();
        $sfclient->expects($this->once())
            ->method('query')
            ->will($this->returnValue($sfresponse));

        return new ClientProxy($sfclient, '', '', '');
    }

    private function mockClientForQueryMore()
    {
        $initialSfResponse = (object)[
            'queryLocator' => 'foo',
            'done' => false,
            'size' => 1,
            'records' => [
                (object)[
                    'type' => 'Account',
                    'Id' => ['00XXXXXX0XXXXXXXXX', '00XXXXXX0XXXXXXXXX'],
                    'any' => [
                        '<sf:Custom_Empty_Field__c xsi:nil="true"/><sf:Custom_Field__c>custom field value</sf:Custom_Field__c>',
                        'Owner' => (object)[
                            'type' => 'User',
                            'Id' => null,
                            'any' => '<sf:Name>Name of the Owner</sf:Name><sf:Email>someone@example.com</sf:Email>"',
                        ],
                    ],
                ],
            ],
        ];

        $moreSfResponse = (object)[
            'queryLocator' => 'foo',
            'done' => true,
            'size' => 1,
            'records' => [
                (object)[
                    'type' => 'Account',
                    'Id' => ['2 00XXXXXX0XXXXXXXXX', '2 00XXXXXX0XXXXXXXXX'],
                    'any' => [
                        '<sf:Custom_Empty_Field__c xsi:nil="true"/><sf:Custom_Field__c>2 custom field value</sf:Custom_Field__c>',
                        'Owner' => (object)[
                            'type' => 'User',
                            'Id' => null,
                            'any' => '<sf:Name>2 Name of the Owner</sf:Name><sf:Email>2someone@example.com</sf:Email>"',
                        ],
                    ],
                ],
            ],
        ];

        $sfclient = $this->getMockBuilder('SforcePartnerClient')->disableOriginalConstructor()->getMock();
        $sfclient->expects($this->once())
            ->method('query')
            ->will($this->returnValue($initialSfResponse));
        $sfclient->expects($this->once())
            ->method('queryMore')
            ->will($this->returnValue($moreSfResponse));

        return new ClientProxy($sfclient, '', '', '');
    }
}
