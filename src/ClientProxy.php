<?php
/**
 * Imagine Easy Solutions LLC, Copyright 2015
 * Modifying, copying, of code contained herein that is not specifically authorized
 * by Imagine Easy Solutions LLC ("Company") is strictly prohibited. Violators will
 * be prosecuted.
 *
 * This restriction applies to proprietary code developed by EasyBib. Code from
 * third-parties or open source projects may be subject to other licensing
 * restrictions by their respective owners.
 *
 * Additional terms can be found at http://www.easybib.com/company/terms
 *
 * @license  http://www.easybib.com/company/terms Terms of Service
 * @link     http://www.imagineeasy.com/
 */
namespace EasyBib\Silex\Salesforce;

use SforcePartnerClient;

class ClientProxy
{
    /** @var SforcePartnerClient  */
    private $client;
    /** @var string */
    private $wsdlpath;
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var bool */
    private $initialized = false;

    /**
     * @param \SforcePartnerClient $client - a not-logged-in salesforce client
     * @param string $wsdlpath
     * @param string $username
     * @param string $password
     */
    public function __construct(SforcePartnerClient $client, $wsdlpath, $username, $password)
    {
        $this->client = $client;
        $this->wsdlpath = $wsdlpath;
        $this->username = $username;
        $this->password = $password;
    }

    public function __call($name, $arguments)
    {
        if (!$this->initialized) {
            $this->client->createConnection($this->wsdlpath);
            $this->client->login($this->username, $this->password);

            $this->initialized = true;
        }
        return call_user_func_array([$this->client, $name], $arguments);
    }
}
