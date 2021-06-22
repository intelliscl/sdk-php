# Intellischool PHP SDK

This SDK has been developed to provide Intellischool partners with a simple and quick means to integrate with our products. 

This is an evolving project. Please report any [issues](https://github.com/intelliscl/sdk-php/issues) using GitHub.


## Installation

```bash
composer require intelliscl/sdk
```
Sample applications can be found in the [sample-apps ](sample-apps/) folder.


## Quickstart

### Instantiation

To instantiate the SDK using an OAuth2 access token:

```php
$idap = \Intellischool\Factory::create('access-token');
```

### LTI Launch

To create an LTI Launch token with the given parameters:

```php
$params = new \Intellischool\Lti\Launch\Token();
$params
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
    
$token = $idap->lti()->getLaunchToken($params);
```

### Data synchronisation

To run a data synchronisation using the Sync Agent:

```php
$syncs = $idap->syncAgent()->doSync();
```
