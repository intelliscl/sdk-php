# Intellischool PHP SDK

This SDK has been developed to provide Intellischool partners with a simple and quick means to integrate with our products. 

This is an evolving project. Please report any [issues](https://github.com/intelliscl/sdk-php/issues) using GitHub.




## Installation

```bash
composer require intelliscl/sdk
```




## Quickstart

### Auth

To initiate an OAuth2 flow:

```php
$authUrl = \Intellischool\OAuth2::getAuthUrl(
    'your_client_id',
    'https://your.redirect.uri/callback',
    ['openid', 'offline_access', 'sync_agent', 'lti_launch']
);

header('Location: '.$authUrl);
```

At your callback/redirect endpoint:

```php
$tokenStore = \Intellischool\OAuth2::exchangeCodeForToken(
    $_GET['code'],
    'https://your.redirect.uri/callback',
    'your_client_id',
    'your_client_secret'
);
```

`$tokenStore` will be populated with a JSON object that you can save in a *very* safe place for use with other endpoints.




## LTI Launch

To create an LTI Launch token with the given parameters:

```php
$token = (new \Intellischool\LTI\LaunchToken())->setIssuer('https://lms.school.edu')
                                               ->setDeploymentId('test_deployment_id')
                                               ->setSubject('jane@school.edu')
                                               ->setName('Ms Jane Marie Doe')
                                               ->setGivenName('Jane')
                                               ->setMiddleName('Marie')
                                               ->setFamilyName('Doe')
                                               ->setEmail('jane@school.edu')
                                               ->setPicture('https://lms.school.edu/jane.jpg')
                                               ->setRole('http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student')
                                               ->setTargetLinkUri('https://analytics.intellischool.cloud/dashboard/12345')
                                               ->setResourceLink(0)
                                               ->setLaunchPresentation('iframe');
$encodedToken = $token->build($key);
```

__Note:__ Roles must be a valid LIS role as per the [LTI specification](https://www.imsglobal.org/spec/lti/v1p3/#role-vocabularies).

Once your token has been generated, it should be `POST`ed in the `id_token` field to our LTI endpoint from the browser:
`https://core.intellischool.net/auth/lti`



## Data synchronisation

The SDK includes a simple version of Intellischool's Sync Agent that automatically synchronises supported local data sources to the Intellischool Data Plaftorm.


### Instantiation

To instantiate the Sync Agent using a deployment ID and secret:

```php
$agent = \Intellischool\SyncAgent::createWithIdAndSecret('deployment_id','deployment_secret');
```

To instantiate the Sync Agent using an OAuth2 token store and your client id & secret:

```php
$agent = \Intellischool\SyncAgent::createWithOAuth2($tokenStore, 'your_client_id', 'your_client_secret');
```

### Executing sync jobs

To run a data synchronisation using the Sync Agent:

```php
$syncs = $agent->doSync();
```

Jobs are managed by the Intellischool Data Platform. Depending on the size of the job(s) the sync process may take seconds to hours. You should ensure that this job is run in the background of your app.
