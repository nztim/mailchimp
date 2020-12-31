<?php declare(strict_types=1);

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpException;
use NZTim\Mailchimp\MailchimpApi;
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
    public function getList()
    {
        $list = $this->api->getList($this->listId);
        $this->assertTrue(is_array($list));
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
    public function getNonexistentList()
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
    public function getMemberSuccess()
    {
        $email = $this->email();
        $this->api->addUpdate($this->listId, $email, [], false);
        $member = $this->api->getMember($this->listId, md5($email));
        $this->assertEquals('subscribed', $member['status']);
        $this->assertEquals($email, $member['email_address']);
        //
        $this->unsub($email);
    }

    /** @test */
    public function getMemberNotFound()
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
    public function addUpdateListDoesntExist()
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
    public function addUpdateNewMemberSuccess()
    {
        $email = $this->email();
        $id = md5($email);
        $this->api->addUpdate($this->listId, $email, ['FNAME' => 'First name', 'LNAME' => 'Last name'], false);
        $member = $this->api->getMember($this->listId, $id);
        $this->assertEquals($id, $member['id']);
        $this->assertEquals('First name', $member['merge_fields']['FNAME']);
        $this->assertEquals('Last name', $member['merge_fields']['LNAME']);
        $this->assertEquals('subscribed', $member['status']);
        //
        $this->unsub($email);
    }

    /** @test */
    public function addUpdateExistingMemberSuccess()
    {
        $email = $this->email();
        $id = md5($email);
        $fname = uniqid();
        $this->api->addUpdate($this->listId, $email, ['FNAME' => $fname], false);
        $member = $this->api->getMember($this->listId, $id);
        $this->assertEquals($fname, $member['merge_fields']['FNAME']);
        //
        $this->unsub($email);
    }

    /** @test */
    public function addUpdateNewMemberConfirmation()
    {
        $this->markTestSkipped();
        $this->api->addUpdate($this->listId,$this->email(), ['FNAME' => 'First name', 'LNAME' => 'Last name'], true);
        // Check email comes through!
    }

    /** @test */
    public function unsubscribe()
    {
        $email = $this->email();
        $id = md5($email);
        $this->api->addUpdate($this->listId, $email, ['FNAME' => 'First name', 'LNAME' => 'Last name'], false);
        $member = $this->api->getMember($this->listId, $id);
        $this->assertEquals('subscribed', $member['status']);
        $this->api->unsubscribe($this->listId, $email);
        $member = $this->api->getMember($this->listId, $id);
        $this->assertEquals('unsubscribed', $member['status']);
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

    private function unsub(string $email): void
    {
        $this->api->unsubscribe($this->listId, $email);
        $this->api->archive($this->listId, $email);
    }
}
