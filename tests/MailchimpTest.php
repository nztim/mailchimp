<?php

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpException;
use NZTim\Mailchimp\Exception\MailchimpInternalErrorException;
use NZTim\Mailchimp\Mailchimp;
use NZTim\Mailchimp\MailchimpApi;
use NZTim\Mailchimp\Member;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MailchimpTest extends TestCase
{
    protected MailchimpApi|MockObject $api;
    protected Mailchimp $mc;

    const LISTID = 'listId';

    /** @before */
    public function prepare()
    {
        $this->api = $this->createMock(MailchimpApi::class);
        $this->mc = new Mailchimp('apikey', $this->api);
    }

    /** @test */
    public function get_lists_empty()
    {
        $this->api->expects($this->once())
            ->method('getLists')
            ->with([])
            ->willReturn([]);
        $this->assertEquals([], $this->mc->getLists());
    }

    /** @test */
    public function get_lists()
    {
        $this->api->expects($this->once())
            ->method('getLists')
            ->with([])
            ->willReturn(['lists' => ['1', '2', '3']]);
        $this->assertEquals(['1', '2', '3'], $this->mc->getLists());
    }

    /** @test */
    public function get_lists_with_params()
    {
        $this->api->expects($this->once())
            ->method('getLists')
            ->with([1, 2, 3])
            ->willReturn(['lists' => ['1', '2', '3']]);
        $this->assertEquals(['1', '2', '3'], $this->mc->getLists([1, 2, 3]));
    }

    /** @test */
    public function status_list_not_found()
    {
        $this->expectException(MailchimpBadRequestException::class);
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willThrowException(new MailchimpBadRequestException());
        $this->api->expects($this->once())
            ->method('responseCodeNotFound')
            ->willReturn(true);
        $this->mc->status(static::LISTID, 'user@example.com');
    }

    /** @test */
    public function status_api_internal_error()
    {
        $this->expectException(MailchimpInternalErrorException::class);
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willThrowException(new MailchimpInternalErrorException('Mailchimp API error: Internal error'));
        $this->api->expects($this->once())
            ->method('responseCodeNotFound')
            ->willReturn(false);
        $this->mc->status(static::LISTID, 'user@example.com');
    }

    /** @test */
    public function status_member_not_found()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willThrowException(new MailchimpBadRequestException());
        $this->api->expects($this->once())
            ->method('responseCodeNotFound')
            ->willReturn(true);
        $this->assertEquals('not found', $this->mc->status(static::LISTID, 'user@example.com'));
    }

    /** @test */
    public function status_unknown_error()
    {
        $this->expectException(MailchimpException::class);
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn([]);
        $this->mc->status(static::LISTID, 'user@example.com');
    }

    /** @test */
    public function status_success()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'subscribed']);
        $this->assertEquals('subscribed', $this->mc->status(static::LISTID, 'user@example.com'));
    }

    /** @test */
    public function check_subscribed()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'subscribed']);
        $this->assertTrue($this->mc->check(static::LISTID, 'user@example.com'));
    }

    /** @test */
    public function check_pending()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'pending']);
        $this->assertFalse($this->mc->check(static::LISTID, 'user@example.com'));
    }

    /** @test */
    public function check_not_subscribed()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'unsubscribed']);
        $this->assertFalse($this->mc->check(static::LISTID, 'user@example.com'));
    }

    /** @test */
    public function subscribe_new_member_confirms_by_default()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willThrowException(new MailchimpBadRequestException());
        $this->api->expects($this->once())
            ->method('responseCodeNotFound')
            ->willReturn(true);
        $this->api->expects($this->once())
            ->method('addUpdate')
            ->with(static::LISTID, 'user@example.com', [], true); // true = confirmation required
        $this->mc->subscribe(static::LISTID, 'user@example.com');
    }

    /** @test */
    public function subscribe_existing_member_turns_off_confirm()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'subscribed']);
        $this->api->expects($this->once())
            ->method('addUpdate')
            ->with(static::LISTID, 'user@example.com', [], false); // Turned off
        $this->mc->subscribe(static::LISTID, 'user@example.com', [], true);
    }

    /** @test */
    public function unsubscribe_not_subscribed()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'unsubscribed']);
        $this->api->expects($this->never())
            ->method('unsubscribe'); // Skipped because already unsubbed
        $this->mc->unsubscribe(static::LISTID, 'test@example.com');
    }

    /** @test */
    public function unsubscribe()
    {
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'subscribed']);
        $this->api->expects($this->once())
            ->method('unsubscribe')
            ->with(static::LISTID, 'test@example.com');
        $this->mc->unsubscribe(static::LISTID, 'test@example.com');
    }

    /** @test */
    public function api_passes_call_through()
    {
        $this->api->expects($this->once())
            ->method('call')
            ->with('someMethod', '/endpoint', ['data' => 123])
            ->willReturn(['test' => 'result']);
        $this->assertEquals(['test' => 'result'], $this->mc->api('someMethod', '/endpoint', ['data' => 123]));
    }

    /** @test */
    public function api_handles_endpoint_without_leading_slash()
    {
        $this->api->expects($this->once())
            ->method('call')
            ->with('get', '/endpoint', [])
            ->willReturn(['data' => 123]);
        $this->assertEquals(['data' => 123], $this->mc->api('get', 'endpoint'));
    }

    /** @test */
    public function add_update_member_new()
    {
        $member = (new Member('test@example.com'));
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willThrowException(new MailchimpBadRequestException());
        $this->api->expects($this->once())
            ->method('responseCodeNotFound')
            ->willReturn(true);
        $this->api->expects($this->once())
            ->method('addUpdateMember')
            ->with(static::LISTID, $member);
        $this->mc->addUpdateMember(static::LISTID, $member);
        $this->assertEquals('pending', $member->parameters()['status_if_new']);
    }

    /** @test */
    public function add_update_member_existing()
    {
        $member = (new Member('test@example.com'));
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID)
            ->willReturn([]);
        $this->api->expects($this->once())
            ->method('getMember')
            ->willReturn(['status' => 'subscribed']);
        $this->api->expects($this->once())
            ->method('addUpdateMember')
            ->with(static::LISTID, $member);
        $this->mc->addUpdateMember(static::LISTID, $member);
        $this->assertEquals('subscribed', $member->parameters()['status']);
    }
}
