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

        $app['salesforce.client.proxy'] = $app->share(function () use ($app) {
            return new ClientProxy(
                new SforcePartnerClient(),
                $app['salesforce.wsdlpath'],
                $app['salesforce.username'],
                $app['salesforce.password']
            );
        });

        $app['salesforce.service'] = $app->share(function () use ($app) {
            return new Service(
                $app['salesforce.client.proxy'],
                $app['salesforce.fieldmap'],
                $app['salesforce.filter'],
                $app['salesforce.upsertfunction'],
                isset($app['salesforce.cleanupfunction']) ? $app['salesforce.cleanupfunction'] : null
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
