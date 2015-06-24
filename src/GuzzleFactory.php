<?php
namespace NZTim\Mailchimp;

use GuzzleHttp\Client;

class GuzzleFactory
{
    public function createClient(array $options)
    {
        return new Client($options);
    }
}