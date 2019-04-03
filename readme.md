# Mailchimp

[![Build Status](https://travis-ci.org/nztim/mailchimp.svg?branch=master)](https://travis-ci.org/nztim/mailchimp)

Basic abstraction with Laravel integration for Mailchimp API v3

### Installation

- `composer require nztim/mailchimp`
- For Laravel support:
    - Laravel 5.5+ will auto-discover the package, for earlier versions you will need to:
        - Add the service provider to `config/app.php`: `NZTim\Mailchimp\MailchimpServiceProvider::class,`
        - Register the facade: `'Mailchimp' => NZTim\Mailchimp\MailchimpFacade::class,`
    - Add an `.env` value for `MC_KEY` (your API key)
    - Optionally, publish the config file:
        - `php artisan vendor:publish --provider=NZTim\Mailchimp\MailchimpServiceProvider`

### Usage

- Within Laravel 5, use the `Mailchimp` facade or inject `NZTim\Mailchimp\Mailchimp` using the container.
- Alternatively, instantiate using the API key: `$mc = new NZTim\Mailchimp\Mailchimp($apikey)`

```php
// Get an array of all available lists:
Mailchimp::getLists();

// Get lists with parameters - get IDs of lists a user is subscribed to:
Mailchimp::getLists(['email' => 'user@example.com', 'fields' => 'lists.id']);

// Check to see if an email address is subscribed to a list:
Mailchimp::check($listId, $emailAddress); // Returns boolean

// Check the staus of a subscriber:
Mailchimp::status($listId, $emailAddress); // Returns 'subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional' or 'not found'

// Adds/updates an existing subscriber:
Mailchimp::subscribe($listId, $emailAddress, $merge = [], $confirm = true);
// Use $confirm = false to skip double-opt-in if you already have permission.
// This method will update an existing subscriber and will not ask an existing subscriber to re-confirm.

// Unsubscribe a member (set status to 'unsubscribed'):
Mailchimp::unsubscribe($listId, $emailAddress);

// Directly call the API:
Mailchimp::api($method, $endpoint, $data = []); // Returns an array.
```

For access to all the member properties available in the v3 API, use the Member class to subscribe and update list members:

```php
$member = (new NZTim\Mailchimp\Member($email))->merge(['FNAME' => 'First name'])->email_type('text')->confirm(false);
Mailchimp::addUpdateMember($member);
```

As with the `subscribe()` method, double-opt-in is default but existing members will not be asked to re-verify so you can use the same methods for create and update without needing to check.

### Errors

- Exceptions are thrown for all errors.
- Networking/communications errors will usually be of the type `Requests_Exception`.
- API errors will be of the base type `NZTim\Mailchimp\MailchimpException`, e.g. incorrect API key, list does not exist.
- `NZTim\Mailchimp\Exception\MailchimpBadRequestException` includes a `response()` method that returns the response body as an array.
- Gotchas: the API throws an error when you:
    - Specify a merge field name with incorrect capitalisation
    - Omit a required merge field when adding a new member

### Examples

```php
// Laravel:
// Subscribe a user to your list, existing subscribers will not receive confirmation emails
Mailchimp::subscribe('listid', 'user@domain.com');

// Subscribe a user to your list with merge fields and double-opt-in confirmation disabled
Mailchimp::subscribe('listid', 'user@domain.com', ['FNAME' => 'First name', 'LNAME' => 'Last name'], false);

// Subscribe/update a user using the Member class
$member = (new NZTim\Mailchimp\Member($email))->interests(['abc123fed' => true])->language('th');
Mailchimp::addUpdateMember('listid', $member);
```


### Upgrading
- To v3.0:
    - Exceptions are now thrown for all errors, use try/catch where necessary
    - Double-opt-in is now the default, update `Mailchimp::subscribe()` as required
