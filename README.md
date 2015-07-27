Salesforce Sync
===============

Queries Salesforce, saves result in local database. Currently only querying
the Salesforce `Account` object.

Contains a console command, a silex service provider, and a standalone service.

## Usage

All example code assumes you're using the included
EasyBib\Silex\Salesforce\SalesforceServiceProvider in a Silex-y kind of app.

### configure

####required

```php
$app['salesforce.username'] = 'username';
$app['salesforce.password'] = 'password accesstoken thingy';

$app['salesforce.fieldmap'] = [
    // the salesforce fields you need => your own local field name
    // see links do salesforce docs at the end of the README
    'Id' => 'id',
    'Owner.Name' => 'owner',
    'Foo_Subscription_Start_Date__c' => 'subscriptionStart',
    'Foo_Name__c' => 'name',
    'Foo_Coupon_Code__c' => 'coupon',
];

// a WHERE/HAVING/LIMIT statement for the salesforce API
// you probably want WHERE roughly like this:
$app['salesforce.filter'] = 'WHERE Foo_Coupon_Code__c != null';

$app['salesforce.upsertfunction'] = $app->protect(function(array $records) use ($app) {
    // UPSERT the salesforce data here, return the number of updated records.
    //
    // Will be called with an array of hash-maps (arrays), with the fields from
    // your fieldmap, e.g:
    //
    //   [
    //      'id' => '123232abcs',
    //      'owner' => 'Some One',
    //      'subscriptionStart' => '1979-01-02',
    //      'name' => NULL,
    //      'coupon' => 'tralalala',
    //   ]
    //
    // This function may get called more than once (once per batch).
    // count($records) will always be more than 0 and less than 2001.
    //
    // You can do what you want here, noSQL, SQL, whatever.
    return $app['em']->getRepository(Entity\Salesforce::class)->batchUpsert($records);
});

```

#### optional

```php
// You only need to set this if you don't use composer.
$app['salesforce.wsdlpath'] = 'path/to/salesforce/partner.wsdl.xml';
```

Once you have all that, register a `new EasyBib\Silex\Salesforce\SalesforceServiceProvider`.

### call

If you use the service provider, you'll have access to the following:

```php
// a Symfony\Component\Console\Command\Command, "salesforce:sync"
// "./console salesforce:sync" syncs all accounts
// "./console salesforce:sync someAccountId123" syncs only that one account
$app['salesforce.command.sync'];

// the EasyBib\Silex\Salesforce\Service, same functionality as the command
$app['salesforce.service'];

// a proxy to a logged-in \SforcePartnerClient, if you're feeling brave
$app['salesforce.client.proxy'];
```

## Docs

### Possible fields to select

* see https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_objects_account.htm#topic-title
* *plus*, there's a weird JOIN-less JOIN stuff, too: https://developer.salesforce.com/blogs/developer-relations/2013/05/basic-soql-relationship-queries.html
* *plus*, of course, custom fields (`Field_Name__c`)
