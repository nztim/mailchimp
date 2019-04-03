<?php

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpInternalErrorException;
use NZTim\Mailchimp\Mailchimp;
use NZTim\Mailchimp\MailchimpApi;
use PHPUnit\Framework\TestCase;

class MailchimpTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var Mockery\Mock */
    protected $api;
    /** @var Mailchimp */
    protected $mc;

    const LISTID = 'listId';

    /** @before */
    public function prepare()
    {
        $this->api = Mockery::mock(MailchimpApi::class);
        $this->mc = new Mailchimp('apikey', $this->api);
    }

    /** @test */
    public function get_lists_empty()
    {
        $this->api->shouldReceive('getLists')->andReturn([]);
        $this->assertEquals([], $this->mc->getLists());
    }

    /** @test */
    public function get_lists()
    {
        $this->api->shouldReceive('getLists')->andReturn(['lists' => ['1', '2', '3']]);
        $this->assertEquals(['1', '2', '3'], $this->mc->getLists());
    }

    /** @test */
    public function get_lists_with_params()
    {
        $this->api->shouldReceive('getLists')->with([1, 2, 3])->andReturn(['lists' => ['1', '2', '3']]);
        $this->assertEquals(['1', '2', '3'], $this->mc->getLists([1, 2, 3]));
    }

    /**
     * @test
     * @expectedException NZTim\Mailchimp\Exception\MailchimpBadRequestException
     * @expectedExceptionMessage Mailchimp API error: list id:listId does not exist
     */
    public function status_list_not_found()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andThrow(new MailchimpBadRequestException);
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->mc->status(self::LISTID, 'user@example.com');
    }

    /**
     * @test
     * @expectedException NZTim\Mailchimp\Exception\MailchimpInternalErrorException
     * @expectedExceptionMessage Mailchimp API error: Internal error
     */
    public function status_api_internal_error()
    {
        $this->api->shouldReceive('getList')
            ->with(self::LISTID)
            ->andThrow(new MailchimpInternalErrorException("Mailchimp API error: Internal error"));
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(false);
        $this->mc->status(self::LISTID, 'user@example.com');
    }

    /** @test */
    public function status_member_not_found()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andThrow(new MailchimpBadRequestException);
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->assertEquals('not found', $this->mc->status(self::LISTID, 'user@example.com'));
    }

    /**
     * @test
     * @expectedException NZTim\Mailchimp\Exception\MailchimpException
     */
    public function status_unknown_error()
    {
        $this->api->shouldReceive('getList')->with('listId')->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn([]);
        $this->mc->status(self::LISTID, 'user@example.com');
    }

    /** @test */
    public function status_success()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->assertEquals('subscribed', $this->mc->status(self::LISTID, 'user@example.com'));
    }

    /** @test */
    public function check_subscribed()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->assertTrue($this->mc->check(self::LISTID, 'user@example.com'));
    }

    /** @test */
    public function check_not_subscribed()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'unsubscribed']);
        $this->assertFalse($this->mc->check(self::LISTID, 'user@example.com'));
    }

    /** @test */
    public function subscribe_new_member_confirms_by_default()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andThrow(new MailchimpBadRequestException);
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->api->shouldReceive('addUpdate')->with(self::LISTID, 'user@example.com', [], true); // true = confirmation required
        $this->mc->subscribe(self::LISTID, 'user@example.com');
    }

    /** @test */
    public function subscribe_existing_member_turns_off_confirm()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->api->shouldReceive('addUpdate')->with(self::LISTID, 'test@example.com', [], false);
        $this->mc->subscribe(self::LISTID, 'test@example.com', [], true);
    }

    /** @test */
    public function unsubscribe_not_subscribed()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'unsubscribed']);
        $this->mc->unsubscribe(self::LISTID, 'test@example.com');
    }

    /** @test */
    public function unsubscribe()
    {
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->api->shouldReceive('unsubscribe')->with(self::LISTID, 'test@example.com');
        $this->mc->unsubscribe(self::LISTID, 'test@example.com');
    }

    /** @test */
    public function api_passes_call_through()
    {
        $this->api->shouldReceive('call')->with('someMethod', '/endpoint', ['data' => 123])->andReturn(['test' => 'result']);
        $this->assertEquals(['test' => 'result'], $this->mc->api('someMethod', '/endpoint', ['data' => 123]));
    }

    /** @test */
    public function api_handles_endpoint_without_leading_slash()
    {
        $this->api->shouldReceive('call')->with('get', '/endpoint', [])->andReturn(['data' => 123])->once();
        $this->assertEquals(['data' => 123], $this->mc->api('get', 'endpoint'));
    }

    /** @test */
    public function add_update_member_new()
    {
        $member = (new \NZTim\Mailchimp\Member('test@example.com'));
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andThrow(new MailchimpBadRequestException);
        $this->api->shouldReceive('responseCodeNotFound')->andReturn(true);
        $this->api->shouldReceive('addUpdateMember')->with(self::LISTID, $member);
        $this->mc->addUpdateMember(self::LISTID, $member);
        $this->assertEquals('pending', $member->parameters()['status_if_new']);
    }

    /** @test */
    public function add_update_member_existing()
    {
        $member = (new \NZTim\Mailchimp\Member('test@example.com'));
        $this->api->shouldReceive('getList')->with(self::LISTID)->andReturn([]);
        $this->api->shouldReceive('getMember')->andReturn(['status' => 'subscribed']);
        $this->api->shouldReceive('addUpdateMember')->with(self::LISTID, $member);
        $this->mc->addUpdateMember(self::LISTID, $member);
        $this->assertEquals('subscribed', $member->parameters()['status']);
    }
}
