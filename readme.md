# Mailchimp

A wrapper and Laravel integration for [Drew McLellan's Mailchimp v3](https://github.com/drewm/mailchimp-api/blob/api-v3/src/MailChimp.php)
 
### Installation

- `composer require nztim/mailchimp`
- For Laravel 5 support:
    - Add the service provider to `config/app.php`: `NZTim\Mailchimp\MailchimpServiceProvider::class,`
    - Register the facade: `'Mailchimp' => NZTim\Mailchimp\MailchimpFacade::class,`
    - Add `.env` value for `MC_KEY` (API key)
    
### Usage
- For Laravel 5, the `Mailchimp` facade or container instantiation is available, API key is retrieved from the `.env` file
- Otherwise, create a new Mailchimp object with `$mailchimp = new Mailchimp($apikey)` 
- `Mailchimp::check($listId, $emailAddress)` checks to see if an email address is subscribed to a list, returns boolean
- `Mailchimp::subscribe($listId, $emailAddress, $mergeFields = [], $confirm = false)` - adds a new subscriber to the list. 
    - $mergeFields - optional array of merge fields
    - $confirm - optional boolean, true = send confirmation email, false = immediately subscribe (permission already obtained) 
- All methods throw `NZTim\Mailchimp\MailchimpException` for problems such as:
    - Networking/communications errors
    - API key incorrect
    - Attempting to check/subscribe to a list that doesn't exist

