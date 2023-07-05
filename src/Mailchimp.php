<?php namespace NZTim\Mailchimp;

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpException;
use Throwable;

class Mailchimp
{
    protected MailchimpApi $api;

    public function __construct($apikey, $api = null)
    {
        if (!is_string($apikey)) {
            throw new MailchimpException("Mailchimp API key is required - use the 'MC_KEY' .env value");
        }
        if (is_null($api)) {
            $api = new MailchimpApi($apikey);
        }
        $this->api = $api;
    }

    public function getLists(array $params = []): array
    {
        $results = $this->api->getLists($params);
        return $results['lists'] ?? [];
    }

    // Determines the status of a subscriber
    // Possible responses:  "subscribed", "unsubscribed", "cleaned", "pending", "transactional", "archived" or "not found".
    public function status(string $listId, string $email): string
    {
        $this->checkListExists($listId);
        $memberId = (new Member($email))->hash();
        try {
            $member = $this->api->getMember($listId, $memberId);
        } catch (Throwable $e) {
            if ($this->api->responseCodeNotFound()) {
                return 'not found';
            }
            throw $e;
        }
        if (!$this->memberStatusIsValid($member)) {
            throw new MailchimpException('Unknown error, status value not found: ' . var_export($member, true));
        }
        return $member['status'];
    }

    // Checks to see if an email address is subscribed to a list
    public function check(string $listId, string $email): bool
    {
        $result = $this->status($listId, $email);
        return $result === 'subscribed';
    }

    // Add a member to the list or update an existing member
    // Ensures that existing subscribers are not asked to reconfirm
    public function subscribe(string $listId, string $email, array $mergeFields = [], bool $confirm = true): void
    {
        if ($this->status($listId, $email) == 'subscribed') {
            $confirm = false;
        }
        $this->api->addUpdate($listId, $email, $mergeFields, $confirm);
    }

    public function addUpdateMember(string $listId, Member $member): void
    {
        if ($this->status($listId, $member->parameters()['email_address']) == 'subscribed') {
            $member->confirm(false);
        }
        $this->api->addUpdateMember($listId, $member);
    }

    public function unsubscribe(string $listId, string $email): void
    {
        if (!$this->check($listId, $email)) {
            return;
        }
        $this->api->unsubscribe($listId, $email);
    }

    public function archive(string $listId, string $email): void
    {
        $this->api->archive($listId, $email);
    }

    public function delete(string $listId, string $email): void
    {
        $this->api->delete($listId, $email);
    }

    // Make an API call directly
    public function api(string $method, string $endpoint, array $data = []): array
    {
        $endpoint = '/' . ltrim($endpoint, '/'); // Ensure leading slash is present
        return $this->api->call($method, $endpoint, $data);
    }

    protected function checkListExists(string $listId): void
    {
        try {
            $this->api->getList($listId);
        } catch (Throwable $e) {
            if ($this->api->responseCodeNotFound()) {
                throw new MailchimpBadRequestException('Mailchimp API error: list id:' . $listId . ' does not exist');
            }
            throw $e;
        }
    }

    protected function memberStatusIsValid($member): bool
    {
        if (!isset($member['status'])) {
            return false;
        }
        return in_array($member['status'], ['subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional', 'archived']);
    }
}
