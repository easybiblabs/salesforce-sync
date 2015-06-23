<?php
/**
 * Copyright (c) 2015 Imagine Easy Solutions LLC
 * MIT licensed, see LICENSE file for details.
 */
namespace EasyBib\Test\Silex\Salesforce;
use EasyBib\Silex\Salesforce;

class ServiceTest extends \PHPUnit_Framework_TestCase
{
    private $sfclient;
    private $fieldmap;

    public function setUp()
    {
        // modelled after the real thing
        $sfresponse = (object)[
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

        $this->sfclient = $this->getMockBuilder('SforcePartnerClient')->disableOriginalConstructor()->getMock();
        $this->sfclient->expects($this->once())
            ->method('query')
            ->will($this->returnValue($sfresponse));

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
            $this->sfclient,
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
}
