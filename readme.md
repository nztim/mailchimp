# Mailchimp

A simple API wrapper for Mailchimp API v3. Includes Laravel 5 support.

### Installation

- `composer require nztim/mailchimp`
- For Laravel 5 support:
    - Add the service provider to `config/app.php`: `'NZTim\Mailchimp\MailchimpServiceProvider',`
    - Register the facade: `'Mailchimp' => 'NZTim\Mailchimp\MailchimpFacade',`
    - Add `.env` value for `MC_KEY` (API key) and `MC_DC` (Datacenter)
    - The Datacenter is the last part of the API key, e.g. `us2`
    
### Usage
- For Laravel 5, the `Mailchimp` facade or container instantiation is available, configuration values coming from the .env file
- Otherwise, create a new Mailchimp object with `$mailchimp = new Mailchimp($apikey, $datacenter)` 
- `Mailchimp::check($listId, $emailAddress)` checks to see if an email address is subscribed to a list, returns boolean
- `Mailchimp::subscribe($listId, $emailAddress, $mergeFields = [], $confirm = false)` - adds a new subscriber to the list. 
    - $mergeFields - optional array of merge fields
    - $confirm - optional boolean, true = send confirmation email, false = immediately subscribe (permission already obtained) 
    - Returns true for success
- All methods throw `NZTim\Mailchimp\MailchimpException` for problems such as:
    - Networking/communications errors
    - API key incorrect
    - Attempting to check/subscribe to a list that doesn't exist

