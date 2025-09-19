<?php namespace NZTim\Mailchimp;

use NZTim\Mailchimp\Exception\MailchimpBadEmailAddressException;
use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpException;
use NZTim\Mailchimp\Exception\MailchimpInternalErrorException;
use NZTim\Mailchimp\Http\Http;
use NZTim\Mailchimp\Http\HttpResponse;

class MailchimpApi
{
    protected string $apikey;
    protected string $baseurl = 'https://<dc>.api.mailchimp.com/3.0';
    protected int $responseCode;

    public function __construct(string $apikey)
    {
        $this->apikey = $apikey;
        $exploded = explode('-', $apikey);
        $this->baseurl = str_replace('<dc>', array_pop($exploded), $this->baseurl);
    }

    // API calls --------------------------------------------------------------

    public function getLists(array $params = []): array
    {
        return $this->call('get', '/lists', $params);
    }

    public function getList(string $listId): array
    {
        return $this->call('get', '/lists/' . $listId);
    }

    public function getMember(string $listId, string $memberId): array
    {
        return $this->call('get', "/lists/{$listId}/members/{$memberId}");
    }

    public function addUpdate(string $listId, string $email, array $merge, bool $confirm): void
    {
        $member = new Member($email);
        $data = [
            'email_address' => $member->email(),
            'status_if_new' => $confirm ? 'pending' : 'subscribed',
            'status'        => $confirm ? 'pending' : 'subscribed',
        ];
        // Empty array doesn't work
        if ($merge) {
            $data['merge_fields'] = $merge;
        }
        $this->call('put', "/lists/{$listId}/members/{$member->hash()}", $data);
    }

    public function addUpdateMember(string $listId, Member $member): void
    {
        $this->call('put', "/lists/{$listId}/members/{$member->hash()}", $member->parameters());
    }

    public function addUpdateMemberSkipMergeValidation(string $listId, Member $member): void
    {
        $this->call('put', "/lists/{$listId}/members/{$member->hash()}?skip_merge_validation=true", $member->parameters());
    }

    public function unsubscribe(string $listId, string $email): void
    {
        $memberId = (new Member($email))->hash();
        $this->call('put', "/lists/{$listId}/members/{$memberId}", ['email_address' => $email, 'status_if_new' => 'unsubscribed', 'status' => 'unsubscribed']);
    }

    public function archive(string $listId, string $email): void
    {
        $memberId = (new Member($email))->hash();
        $this->call('delete', "/lists/{$listId}/members/{$memberId}", ['email_address' => $email]);
    }

    public function delete(string $listId, string $email): void
    {
        $memberId = (new Member($email))->hash();
        $this->call('post', "/lists/{$listId}/members/{$memberId}/actions/delete-permanent");
    }

    public function getTags(string $listId, string $email): array
    {
        $email = strtolower($email);
        $memberId = md5($email);
        return $this->call('get', "/lists/{$listId}/members/{$memberId}/tags");
    }

    public function addTags(string $listId, string $email, array $tags): void
    {
        $email = strtolower($email);
        $memberId = md5($email);
        $data = [
            'tags' => array_map(function ($tag) {
                return ["name" => $tag, "status" => "active"];
            }, $tags),
        ];
        $this->call('post', "/lists/{$listId}/members/{$memberId}/tags", $data);
    }

    public function removeTags(string $listId, string $email, array $tags): void
    {
        $email = strtolower($email);
        $memberId = md5($email);
        $data = [
            'tags' => array_map(function ($tag) {
                return ["name" => $tag, "status" => "inactive"];
            }, $tags),
        ];
        $this->call('post', "/lists/{$listId}/members/{$memberId}/tags", $data);
    }

    // HTTP -------------------------------------------------------------------

    public function call(string $method, string $endpoint, array $data = []): array
    {
        $method = trim(strtolower($method));
        if (!in_array($method, ['get', 'put', 'post', 'delete', 'patch'])) {
            throw new MailchimpException('Invalid API call method: ' . $method);
        }
        $url = $this->baseurl . $endpoint;
        if (in_array($method, ['get', 'delete'])) {
            $url .= $data ? '?' . http_build_query($data) : '';
            $response = (new Http())->withBasicAuth('mcuser', $this->apikey)->$method($url);
        } else {
            $response = (new Http())->withBasicAuth('mcuser', $this->apikey)->$method($url, $data);
        }
        /** @var HttpResponse $response */
        $this->responseCode = $response->status();
        if ($this->responseCode >= 400) {
            $this->apiError($response);
        }
        return $response->json();
    }

    protected function apiError(HttpResponse $response): void
    {
        $data = $response->json();
        $info = var_export($data, true);
        $message = "Mailchimp API error (" . $response->status() . "): " . $info;
        // Check for "...has signed up to a lot of lists very recently; we\'re not allowing more signups for now"
        if ($data['status'] === 400 &&
            $data['title'] !== 'Invalid Resource' &&
            str_contains($data['detail'], 'has signed up to a lot of lists very recently')) {
            throw new MailchimpBadEmailAddressException($message, $this->responseCode);
        }
        // Categorise other errors
        if ($this->responseCode <= 499) {
            throw new MailchimpBadRequestException($message, $this->responseCode, null, $response->body());
        }
        throw new MailchimpInternalErrorException($message, $this->responseCode);
    }

    public function responseCode(): int
    {
        return $this->responseCode;
    }

    public function responseCodeNotFound(): bool
    {
        return $this->responseCode == 404;
    }
}
