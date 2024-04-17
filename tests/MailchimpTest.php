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

    /** @test */
    public function status_method_lowercases_and_trims_email_addresses()
    {
        $email = '   TEST@example.com ';
        $trimmedLowercasedEmail = trim(strtolower($email));
        $correctSubscriberHash = md5($trimmedLowercasedEmail);
        $this->api->expects($this->once())
            ->method('getList')
            ->with(static::LISTID);
        $this->api->expects($this->once())
            ->method('getMember')
            ->with(static::LISTID, $correctSubscriberHash)
            ->willReturn(['status' => 'subscribed']);
        $this->assertEquals('subscribed', $this->mc->status(static::LISTID, $email));
    }

    /** @test */
    public function add_tags_success()
    {
        $email = 'test@example.com';
        $tags = ['a', 'b', 'c'];
        $this->api->expects($this->once())
            ->method('addTags')
            ->with(static::LISTID, $email, $tags);
        $this->mc->addTags(static::LISTID, $email, $tags);
    }

    /** @test */
    public function add_tags_skips_non_strings()
    {
        $email = 'test@example.com';
        $tags = [1, 2, 'c'];
        $this->api->expects($this->once())
            ->method('addTags')
            ->with(static::LISTID, $email, ['c']);
        $this->mc->addTags(static::LISTID, $email, $tags);
    }

    /** @test */
    public function add_tags_does_not_call_api_if_list_is_empty()
    {
        $email = 'test@example.com';
        $tags = [1, 2, 3];
        $this->api->expects($this->never())
            ->method('addTags');
        $this->mc->addTags(static::LISTID, $email, $tags);
    }

    /** @test */
    public function get_tags_empty()
    {
        $email = 'test@example.com';
        $this->api->expects($this->once())
            ->method('getTags')
            ->willReturn([]);
        $response = $this->mc->getTags(static::LISTID, $email);
        $this->assertEquals([], $response);
    }

    /** @test */
    public function get_tags_with_results()
    {
        $tagData = ['id' => 1234, 'name' => 'tag1', 'date_added' => '2024-04-17T19:22:18+00:00'];
        $email = 'test@example.com';
        $this->api->expects($this->once())
            ->method('getTags')
            ->willReturn(['tags' => [$tagData]]);
        $response = $this->mc->getTags(static::LISTID, $email);
        $this->assertEquals($tagData['id'], $response[0]->id);
        $this->assertEquals($tagData['name'], $response[0]->name);
        $this->assertEquals($tagData['date_added'], $response[0]->dateAdded);
    }

    /** @test */
    public function remove_tags_success()
    {
        $email = 'test@example.com';
        $tags = ['a', 'b', 'c'];
        $this->api->expects($this->once())
            ->method('removeTags')
            ->with(static::LISTID, $email, $tags);
        $this->mc->removeTags(static::LISTID, $email, $tags);
    }

    /** @test */
    public function remove_tags_skips_non_strings()
    {
        $email = 'test@example.com';
        $tags = [1, 2, 'c'];
        $this->api->expects($this->once())
            ->method('removeTags')
            ->with(static::LISTID, $email, ['c']);
        $this->mc->removeTags(static::LISTID, $email, $tags);
    }

    /** @test */
    public function remove_tags_does_not_call_api_if_list_is_empty()
    {
        $email = 'test@example.com';
        $tags = [1, 2, 3];
        $this->api->expects($this->never())
            ->method('removeTags');
        $this->mc->removeTags(static::LISTID, $email, $tags);
    }

    /** @test */
    public function remove_all_tags_success()
    {
        $tag1Data = ['id' => 1234, 'name' => 'tag1', 'date_added' => '2024-04-17T19:22:18+00:00'];
        $tag2Data = ['id' => 5678, 'name' => 'tag2', 'date_added' => '2024-04-17T19:22:18+00:00'];
        $email = 'test@example.com';
        $this->api->expects($this->once())
            ->method('getTags')
            ->willReturn(['tags' => [$tag1Data, $tag2Data]]);
        $this->api->expects($this->once())
            ->method('removeTags')
            ->with(static::LISTID, $email, ['tag1', 'tag2']);
        $this->mc->removeAllTags(static::LISTID, $email);
    }
}
