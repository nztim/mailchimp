<?php

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Mailchimp;
use NZTim\Mailchimp\MailchimpApi;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private Mailchimp $mc;
    private string $listId;
    private string $domain;

    /** @before */
    public function prepare()
    {
        $secrets = require(__DIR__ . '/../secrets.php');
        if ($secrets['apitest'] !== true) {
            $this->markTestSkipped();
        }
        $this->mc = new Mailchimp('', new MailchimpApi($secrets['key']));
        $this->listId = $secrets['list'];
        $this->domain = $secrets['domain'];
    }

    /** @test */
    public function member_status_lifecycle()
    {
        $email = $this->email();
        $this->assertEquals('not found', $this->mc->status($this->listId, $email));
        $this->mc->subscribe($this->listId, $email, [], false);
        $this->assertEquals('subscribed', $this->mc->status($this->listId, $email));
        $this->mc->archive($this->listId, $email);
        $this->assertEquals('archived', $this->mc->status($this->listId, $email));
        $this->mc->delete($this->listId, $email);
        $this->assertEquals('not found', $this->mc->status($this->listId, $email));
        try {
            $this->mc->subscribe($this->listId, $email, [], false);
        } catch (MailchimpBadRequestException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertTrue(strpos($e->getMessage(), 'Forgotten Email Not Subscribed') !== false);
        }
    }

    private function email(): string
    {
        return strtolower(uniqid(bin2hex(random_bytes(2)))) . '@' . $this->domain;
    }
}
