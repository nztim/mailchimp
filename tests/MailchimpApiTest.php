<?php declare(strict_types=1);

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpException;
use NZTim\Mailchimp\MailchimpApi;
use NZTim\Mailchimp\Member;
use PHPUnit\Framework\TestCase;

class MailchimpApiTest extends TestCase
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
    public function callInvalidMethod()
    {
        $this->expectException(MailchimpException::class);
        $this->api->call('invalid', 'lists');
    }

    /** @test */
    public function get_list()
    {
        $list = $this->api->getList($this->listId);
        $this->assertEquals($this->listId, $list['id']);
    }

    /** @test */
    public function get_lists()
    {
        $response = $this->api->getLists();
        $this->assertTrue(is_array($response['lists']));
        $found = false;
        foreach($response['lists'] as $list) {
            if ($list['id'] === $this->listId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /** @test */
    public function get_lists_with_params()
    {
        $response = $this->api->getLists(['count' => 1]);
        $list = $response['lists'][0];
        $this->assertTrue(count(array_keys($list)) > 1); // Normal response contains all details of the list
        //
        $response = $this->api->getLists(['count' => 1, 'fields' => 'lists.id']); // Request only the list ID
        $list = $response['lists'][0];
        $this->assertTrue(count(array_keys($list)) == 1); // Should be just one value here, no further information
    }

    /** @test */
    public function get_nonexistent_list()
    {
        try {
            $this->api->getList('invalid');
            $this->assertTrue($this->api->responseCodeNotFound());
        } catch (MailchimpBadRequestException $e) {
            $this->assertEquals(404, $e->response()['status']);
            return;
        }
        $this->fail("Exception not thrown");
    }

    /** @test */
    public function get_member_success()
    {
        $email = $this->email();
        $hash = (new Member($email))->hash();
        $this->api->addUpdate($this->listId, $email, [], false);
        $member = $this->api->getMember($this->listId, $hash);
        $this->assertEquals('subscribed', $member['status']);
        $this->assertEquals($email, $member['email_address']);
        //
        $this->unsub($email);
    }

    /** @test */
    public function get_member_not_found()
    {
        try {
            $this->api->getMember($this->listId, md5(uniqid()));
        } catch (MailchimpBadRequestException $e) {
            $this->assertEquals(404, $this->api->responseCode());
            $this->assertTrue($this->api->responseCodeNotFound());
            $this->assertEquals(404, $e->getCode());
            $this->assertEquals(404, $e->response()['status']);
            return;
        }
        $this->fail('Exception not thrown');
    }

    /** @test */
    public function add_update_list_doesnt_exist()
    {
        try {
            $this->api->addUpdate('non-existent-list', $this->email(), [], false);
        } catch (MailchimpBadRequestException $e) {
            $this->assertTrue($this->api->responseCodeNotFound());
            $this->assertEquals(404, $e->response()['status']);
            return;
        }
        $this->fail('Exception not thrown');
    }

    /** @test */
    public function add_update_new_member_success()
    {
        $email = $this->email();
        $hash = (new Member($email))->hash();
        $this->api->addUpdate($this->listId, $email, ['FNAME' => 'First name', 'LNAME' => 'Last name'], false);
        $member = $this->api->getMember($this->listId, $hash);
        $this->assertEquals($hash, $member['id']);
        $this->assertEquals('First name', $member['merge_fields']['FNAME']);
        $this->assertEquals('Last name', $member['merge_fields']['LNAME']);
        $this->assertEquals('subscribed', $member['status']);
        //
        $this->unsub($email);
    }

    /** @test */
    public function add_update_existing_member_success()
    {
        $email = $this->email();
        $hash = (new Member($email))->hash();
        $fname = uniqid();
        $this->api->addUpdate($this->listId, $email, ['FNAME' => $fname], false);
        $member = $this->api->getMember($this->listId, $hash);
        $this->assertEquals($fname, $member['merge_fields']['FNAME']);
        //
        $this->unsub($email);
    }

    /** @test */
    public function add_update_existing_member_email_is_lowercased_and_trimmed()
    {
        $email = $this->emailWithUppercaseAndSpaces();
        // Create a new member
        $member = new Member($email);
        $fname = uniqid();
        $this->api->addUpdate($this->listId, $member->email(), ['FNAME' => $fname], false);
        $memberDetails = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals($fname, $memberDetails['merge_fields']['FNAME']);
        // Update existing member without normalising the email address
        $fname2 = uniqid();
        $this->api->addUpdate($this->listId, $email, ['FNAME' => $fname2], false); // <-- Here original $email is used
        $memberDetails = $this->api->getMember($this->listId, $member->hash());
        $this->assertEquals($fname2, $memberDetails['merge_fields']['FNAME']);
    }

    /** @test */
    public function add_update_new_member_confirmation()
    {
        $this->markTestSkipped("This tests that the confirmation email is sent");
        $this->api->addUpdate($this->listId, $this->email(), ['FNAME' => 'First name', 'LNAME' => 'Last name'], true);
    }

    /** @test */
    public function unsubscribe_success()
    {
        $email = $this->email();
        $this->subscriberUserAndConfirm($email);
        //
        $this->api->unsubscribe($this->listId, $email);
        $member = $this->api->getMember($this->listId, (new Member($email))->hash());
        $this->assertEquals('unsubscribed', $member['status']);
    }

    /** @test */
    public function unsubscribe_success_with_uppercase_and_spaces()
    {
        $email = $this->emailWithUppercaseAndSpaces();
        $this->subscriberUserAndConfirm($email);
        // Now unsubscribe using the original $email, ensure it is processed before use with the api.
        $this->api->unsubscribe($this->listId, $email);
        $member = $this->api->getMember($this->listId, (new Member($email))->hash());
        $this->assertEquals('unsubscribed', $member['status']);
    }

    /** @test */
    public function archive_with_uppercase_and_spaces()
    {
        $email = $this->emailWithUppercaseAndSpaces();
        $this->subscriberUserAndConfirm($email);
        // Now unsub & archive using the original $email, ensure it is processed before use with the api.
        $this->api->unsubscribe($this->listId, $email);
        $this->api->archive($this->listId, $email);
        $member = $this->api->getMember($this->listId, (new Member($email))->hash());
        $this->assertEquals('archived', $member['status']);
    }

    /** @test */
    public function delete_with_uppercase_and_spaces()
    {
        $email = $this->emailWithUppercaseAndSpaces();
        $this->subscriberUserAndConfirm($email);
        // Now unsub, archive and delete using the original $email, ensure it is processed before use with the api.
        $this->api->unsubscribe($this->listId, $email);
        $this->api->archive($this->listId, $email);
        $this->api->delete($this->listId, $email);
        // Now try and find
        try {
            $this->api->getMember($this->listId, (new Member($email))->hash());
        } catch (MailchimpBadRequestException $e) {
            $this->assertEquals(404, $this->api->responseCode());
            $this->assertTrue($this->api->responseCodeNotFound());
            $this->assertEquals(404, $e->getCode());
            $this->assertEquals(404, $e->response()['status']);
            return;
        }
        $this->fail('Exception not thrown');
    }

    /** @test */
    public function data_is_passed_via_query_string_for_get_request()
    {
        // Subscribe 12 addresses
        $addresses = [];
        $i = 12;
        while ($i) {
            $email = $this->email();
            $addresses[] = $email;
            $this->api->addUpdate($this->listId, $email, [], false);
            $i--;
        }
        // Check query params are passed successfully
        $endpoint = '/lists/' . $this->listId . '/members';
        $response = $this->api->call('get', $endpoint);
        $this->assertCount(10, $response['members']); // Defaults is 10 per page
        $response = $this->api->call('get', $endpoint, ['count' => 5 ]);
        $this->assertCount(5, $response['members']);
        // Remove the members
        foreach($addresses as $email) {
            $this->unsub($email);
        }
    }

    private function email(): string
    {
        return strtolower(uniqid(bin2hex(random_bytes(2)))) . '@' . $this->domain;
    }

    private function emailWithUppercaseAndSpaces(): string
    {
        return "  UserName" . rand(1000, 99999) . '@' . $this->domain;
    }

    private function subscriberUserAndConfirm(string $email): void
    {
        $hash = (new Member($email))->hash();
        $this->api->addUpdate($this->listId, $email, ['FNAME' => 'First name', 'LNAME' => 'Last name'], false);
        $member = $this->api->getMember($this->listId, $hash);
        $this->assertEquals('subscribed', $member['status']);
    }

    private function unsub(string $email): void
    {
        $this->api->unsubscribe($this->listId, $email);
        $this->api->archive($this->listId, $email);
    }
}
