<?php
/**
 * Copyright (c) 2015 Imagine Easy Solutions LLC
 * MIT licensed, see LICENSE file for details.
 */
namespace EasyBib\Silex\Salesforce;

use SforcePartnerClient;
use Silex\Application;
use Silex\ServiceProviderInterface;

class SalesforceServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        if (empty($app['salesforce.wsdlpath'])) {
            // default assumption: salesforce library is installed next to us in vendor
            $app['salesforce.wsdlpath'] = dirname(dirname(dirname(__DIR__))) . '/developerforce/force.com-toolkit-for-php/soapclient/partner.wsdl.xml';
        }

        $app['salesforce.client'] = $app->share(function () use ($app) {
            $client = new SforcePartnerClient();
            $client->createConnection($app['salesforce.wsdlpath']);
            $client->login($app['salesforce.username'], $app['salesforce.password']);
            return $client;
        });

        $app['salesforce.service'] = $app->share(function () use ($app) {
            return new Service(
                $app['salesforce.client'],
                $app['salesforce.fieldmap'],
                $app['salesforce.filter'],
                $app['salesforce.upsertfunction']
            );
        });

        $app['salesforce.command.sync'] = $app->share(function () use ($app) {
            return new SyncCommand($app['salesforce.service']);
        });
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function boot(Application $app)
    {
    }
}
