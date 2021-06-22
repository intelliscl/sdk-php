# Intellischool PHP SDK

This SDK has been developed to provide Intellischool partners with a simple and quick means to integrate with our products. 

This is an evolving project. Please report any [issues](https://github.com/intelliscl/sdk-php/issues) using GitHub.


## Installation

```bash
composer require intelliscl/sdk
```
Sample applications can be found in the [sample-apps](sample-apps/) folder.


## Quickstart

### Auth

To initiate an OAuth2 flow:

```php
$authUrl = \Intellischool\OAuth2::getAuthUrl(
    'your_client_id',
    'https://your.redirect.uri/callback',
    'openid offline_access sync_agent lti_launch'
);

header('Location: '.$authUrl);
```

At your callback/redirect endpoint:

```php
$tokenStore = \Intellischool\OAuth2::createToken(
    'authorization_code',
    $_GET['code'],
    'https://your.redirect.uri/callback',
    'your_client_id',
    'your_client_secret'
);
```

`$tokenStore` will be populated with a JSON object that you can save in an appropriate place for use with other endpoints.


### Instantiation

To instantiate the SDK using an OAuth2 access token:

```php
$idap = \Intellischool\Factory::create($tokenStore);
```


### LTI Launch

To create an LTI Launch token with the given parameters:

```php
$params = new \Intellischool\Lti\LaunchToken\Params();
$params
    ->setIssuer('https://lms.school.edu')
    ->setSubject('jane@school.edu')
    ->setName('Ms Jane Marie Doe')
    ->setGivenName('Jane')
    ->setMiddleName('Marie')
    ->setFamilyName('Doe')
    ->setEmail('jane@school.edu')
    ->setPicture('https://lms.school.edu/jane.jpg')
    ->setPersonId('person_id_in_external_system')
    ->setRole('student')
    ->setTarget('https://analytics.intellischool.cloud/dashboard/12345')
    ->setLaunchPresentation('iframe');
    
$token = \Intellischool\Lti\LaunchToken::generate($params,'your_rsa_secret_key');
```


## Data synchronisation

The SDK includes a simple version of Intellischool's Sync Agent that automatically synchronises supported local data sources to the Intellischool Data Plaftorm.


## Instantiation

To instantiate the Sync Agent using a deployment ID and secret:

```php
$agent = \Intellischool\SyncAgent::createWithIdAndSecret('deployment_id','deployment_secret');
```

To instantiate the Sync Agent using an OAuth2 token store:

```php
$agent = \Intellischool\SyncAgent::createWithOAuth2($tokenStore);
```

## Executing sync jobs

To run a data synchronisation using the Sync Agent:

```php
$syncs = $agent->doSync();
```

Jobs are managed by the Intellischool Data Platform. Depending on the size of the job(s) the sync process may take seconds to hours. You should ensure that this job is run in the background of your app.
