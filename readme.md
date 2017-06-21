# Mailchimp

Basic abstraction with Laravel integration for Mailchimp API v3
 
### Installation

- `composer require nztim/mailchimp`
- For Laravel 5 support:
    - Add the service provider to `config/app.php`: `NZTim\Mailchimp\MailchimpServiceProvider::class,`
    - Register the facade: `'Mailchimp' => NZTim\Mailchimp\MailchimpFacade::class,`
    - Add `.env` value for `MC_KEY` (API key)
    - Optionally publish the config file: `php artisan vendor:publish --provider=NZTim\Mailchimp\MailchimpServiceProvider`
    
### Usage
- Within Laravel 5, use the `Mailchimp` facade or instantiate via the container `app(NZTim\Mailchimp\Mailchimp::class)`.
    - Alternatively, instantiate manually using the API key in the constructor: `$mc = new NZTim\Mailchimp\Mailchimp($apikey)`

- `Mailchimp::check($listId, $emailAddress)` checks to see if an email address is subscribed to a list, returns boolean
- `Mailchimp::status($listId, $emailAddress)` determines the status of a subscriber, possible responses: 'subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional' or 'not found'
- `Mailchimp::subscribe($listId, $emailAddress, $mergeFields = [], $confirm = true)` - adds a new subscriber to the list, or updates an existing subscriber. 
    - $mergeFields - optional array of merge fields
    - $confirm - optional boolean, true = double-opt-in, false = immediately subscribe (permission already obtained)
    - This method ensures that existing subscribers are updated but not asked to reconfirm their subscription.
- `Mailchimp::getLists()` returns an array of all available lists.
- `Mailchimp::api($method, $endpoint, $data = [])` make a call directly to the API. The endpoint should have a leading '/' and the return value is an array.

### Errors

- Exceptions are thrown for all errors.
- Networking/communications errors will usually be of the type `Requests_Exception`. 
- API errors will be of the base type `NZTim\Mailchimp\MailchimpException`, e.g. incorrect API key, list does not exist.
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
```


### Upgrading
- To v3.0:
    - Exceptions are now thrown for all errors, use try/catch where necessary
    - Double-opt-in is now the default, update `Mailchimp::subscribe()` as required
