<?php
/**
 * Copyright (c) 2016 Chegg Inc.
 * Apache-2.0 licensed, see LICENSE.txt file for details.
 *
 * @license  Apache-2.0
 * @link     http://www.apache.org/licenses/LICENSE-2.0
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
