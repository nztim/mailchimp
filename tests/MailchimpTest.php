<?php

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpInternalErrorException;
use NZTim\Mailchimp\Mailchimp;
use NZTim\Mailchimp\MailchimpApi;

class MailchimpTest extends PHPUnit_Framework_TestCase
{
    /** @var Mockery\Mock */
    protected $api;
    /** @var Mailchimp */
    protected $mc;

    /** @before */
    public function prepare()
    {
        $this->api = Mockery::mock(MailchimpApi::class);
        $this->mc = new Mailchimp('apikey', $this->api);
    }

    /**
     * @test
     * @expectedException NZTim\Mailchimp\Exception\MailchimpBadRequestException
     * @expectedExceptionMessage Mailchimp API error: list id:invalid does not exist
     */
    public function statusListNotFound()
    {
        $listId = 'invalid';
        $this->api->shouldReceive('getList')->with($listId)->andThrow(new MailchimpBadRequestException());
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->assertFalse($this->mc->status($listId, 'name@example.com'));
    }

    /**
     * @test
     * @expectedException NZTim\Mailchimp\Exception\MailchimpInternalErrorException
     * @expectedExceptionMessage Mailchimp API error: Internal error
     */
    public function statusApiInternalError()
    {
        $listId = 'invalid';
        $this->api->shouldReceive('getList')
            ->with($listId)
            ->andThrow(new MailchimpInternalErrorException("Mailchimp API error: Internal error"));
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(false);
        $this->assertFalse($this->mc->status($listId, 'name@example.com'));
    }

    /** @test */
    public function statusMemberNotFound()
    {
        $listId = 'listId';
        $this->api->shouldReceive('getList')->with($listId)->andReturn([]);
        $this->api->shouldReceive('getMember')->andThrow(new MailchimpBadRequestException);
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->assertEquals('not found', $this->mc->status($listId, 'notfound@example.com'));
    }

    /**
     * @test
     * @expectedException NZTim\Mailchimp\Exception\MailchimpException
     */
    public function statusUnknownError()
    {
        $listId = 'listId';
        $this->api->shouldReceive('getList')->with($listId)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn([]);
        $this->assertEquals('not found', $this->mc->status($listId, 'notfound@example.com'));
    }

    /** @test */
    public function statusSuccess()
    {
        $listId = 'listId';
        $this->api->shouldReceive('getList')->with($listId)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->assertEquals('subscribed', $this->mc->status($listId, 'notfound@example.com'));
    }

    /** @test */
    public function subscribeNewMemberConfirmsByDefault()
    {
        $listId = 'listId';
        $email = 'test@example.com';
        $this->api->shouldReceive('getList')->with($listId)->andReturn([]);
        $this->api->shouldReceive('getMember')->andThrow(new MailchimpBadRequestException());
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->api->shouldReceive('addUpdateMember')->with($listId, $email, [], true);
        $this->mc->subscribe($listId, $email);
    }

    /** @test */
    public function subscribeExistingMemberTurnsOffConfirm()
    {
        $listId = 'listId';
        $email = 'test@example.com';
        $this->api->shouldReceive('getList')->with($listId)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->api->shouldReceive('addUpdateMember')->with($listId, $email, [], false);
        $this->mc->subscribe($listId, $email, [], true);
    }

    /** @test */
    public function getLists()
    {
        $this->api->shouldReceive('getLists')->andReturn([]);
        $this->mc->getLists();
    }
}
