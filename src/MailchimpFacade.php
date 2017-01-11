<?php namespace NZTim\Mailchimp;

use Illuminate\Support\Facades\Facade;

class MailchimpFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mailchimp';
    }
}
