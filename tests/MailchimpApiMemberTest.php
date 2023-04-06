<?php declare(strict_types=1);

use NZTim\Mailchimp\MailchimpApi;
use NZTim\Mailchimp\Member;
use PHPUnit\Framework\TestCase;

class MailchimpApiMemberTest extends TestCase
{
    private MailchimpApi $api;
    private string $listId;
    private string $domain;

    /** @before */
    public function prepare()
    {
        $secrets = require(__DIR__ . '/../secrets.php');
        if ($secrets['apitest'] !== true) {
            $this->markTestSkipped();
        }
        $this->api = new MailchimpApi($secrets['key']);
        $this->listId = $secrets['list'];
        $this->domain = $secrets['domain'];
    }

    /** @test */
    public function subscribe_minimal()
    {
        $member = (new Member($this->email()))->confirm(false);
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals($member->email(), $data['email_address']);
        $this->unsub($member);
    }

    /** @test */
    public function subscribe_confirmed()
    {
        $member = (new Member($this->email()))->confirm(false);
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals('subscribed', $data['status']);
        $this->unsub($member);
    }

    /** @test */
    public function email_type()
    {
        $member = (new Member($this->email()))->confirm(false)->email_type('text');
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals('text', $data['email_type']);
        $this->unsub($member);
    }

    /** @test */
    public function member_status()
    {
        $member = (new Member($this->email()));
        $this->api->addUpdateMember($this->listId, $member);
        $member->status('unsubscribed');
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals('unsubscribed', $data['status']);
        $this->unsub($member);
    }

    /** @test */
    public function merge_fields()
    {
        $merge = ['FNAME' => $this->rand(), 'LNAME' => $this->rand()];
        $member = (new Member($this->email()))->merge_fields($merge);
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals($merge['FNAME'], $data['merge_fields']['FNAME']);
        $this->assertEquals($merge['LNAME'], $data['merge_fields']['LNAME']);
        $this->unsub($member);
    }

    // Added a group to the test list with three options, then included in the member data was this:
    // ["interests" => ["ac16479d5f" => false, "d9e354e0b4" => false, "6f029937b7" => false ]]
    // This test not currently working
    /** @test */
    public function interests()
    {
        $this->markTestSkipped();
        $interests = ["ac16479d5f" => true, "d9e354e0b4" => true, "6f029937b7" => false ];
        $member = (new Member($this->email()))->interests($interests);
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals(true, $data['interests']['ac16479d5f']);
        $this->assertEquals(true, $data['interests']['d9e354e0b4']);
        $this->assertEquals(false, $data['interests']['6f029937b7']);
        $this->unsub($member);
    }

    /** @test */
    public function language()
    {
        $member = (new Member($this->email()))->confirm(false)->language('th');
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals('th', $data['language']);
        $this->unsub($member);
    }

    /** @test */
    public function vip()
    {
        $member = (new Member($this->email()))->confirm(false)->vip(true);
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals(true, $data['vip']);
        $this->unsub($member);
    }

    /** @test */
    public function location()
    {
        $member = (new Member($this->email()))->confirm(false)->location(-36.786992, 174.858770);
        $this->api->addUpdateMember($this->listId, $member);
        $data = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals(-36.786992, $data['location']['latitude']);
        $this->assertEquals(174.858770, $data['location']['longitude']);
        $this->unsub($member);
    }

    private function email(): string
    {
        return $this->rand() . '@' . $this->domain;
    }

    private function rand(): string
    {
        return strtolower(uniqid(bin2hex(random_bytes(2))));
    }

    private function unsub(Member $member): void
    {
        $this->api->unsubscribe($this->listId, $member->email());
        $this->api->archive($this->listId, $member->email());
    }
}
