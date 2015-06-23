<?php
namespace NZTim\Mailchimp;

use Exception;

class MailchimpException extends Exception
{
    protected $userMessage;

    public function setUserMessage($message)
    {
        $this->userMessage = $message;
    }

    public function getUserMessage()
    {
        return $this->userMessage;
    }
}