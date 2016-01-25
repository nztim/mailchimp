# Mailchimp

A wrapper and Laravel integration for [Drew McLellan's Mailchimp v3](https://github.com/drewm/mailchimp-api/blob/api-v3/src/MailChimp.php)
 
### Installation

- `composer require nztim/mailchimp`
- For Laravel 5 support:
    - Add the service provider to `config/app.php`: `NZTim\Mailchimp\MailchimpServiceProvider::class,`
    - Register the facade: `'Mailchimp' => NZTim\Mailchimp\MailchimpFacade::class,`
    - Add `.env` value for `MC_KEY` (API key)
    
### Usage
- For Laravel 5, the `Mailchimp` facade or container instantiation is available, this requires the `.env` value for the API key
- `Mailchimp::check($listId, $emailAddress)` checks to see if an email address is subscribed to a list, returns boolean
- `Mailchimp::subscribe($listId, $emailAddress, $mergeFields = [], $confirm = false)` - adds a new subscriber to the list. 
    - $mergeFields - optional array of merge fields
    - $confirm - optional boolean, true = send confirmation email, false = immediately subscribe (permission already obtained) 
- Errors
    - All methods return false for errors
    - Check `Mailchimp::error()` after a request to see if there was a problem.  
    - Typical errors include networking/communications, incorrect API key, list doesn't exist
- Gotchas: the API throws an error when you
    - Specify a merge field name with incorrect capitalisation
    - Omit a required merge field when adding a new member 
