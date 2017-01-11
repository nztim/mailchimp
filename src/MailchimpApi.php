<?php namespace NZTim\Mailchimp;

use NZTim\Mailchimp\Exception\MailchimpBadRequestException;
use NZTim\Mailchimp\Exception\MailchimpException;
use NZTim\Mailchimp\Exception\MailchimpInternalErrorException;
use Requests;
use Requests_Auth_Basic;
use Requests_Response;
use Throwable;

class MailchimpApi
{
    protected $apikey;
    protected $baseurl = 'https://<dc>.api.mailchimp.com/3.0';
    protected $responseCode;

    public function __construct(string $apikey)
    {
        $this->apikey = $apikey;
        $exploded = explode('-', $apikey);
        $this->baseurl = str_replace('<dc>', array_pop($exploded), $this->baseurl);
    }

    // API calls --------------------------------------------------------------

    public function getList(string $listId): array
    {
        return $this->call('get', '/lists/' . $listId);
    }

    public function getMember(string $listId, string $memberId): array
    {
        return $this->call('get', "/lists/{$listId}/members/{$memberId}");
    }

    public function addUpdateMember(string $listId, string $email, array $merge, bool $confirm)
    {
        $email = strtolower($email);
        $memberId = md5($email);
        $data = [
            'email_address' => $email,
            'status_if_new' => $confirm ? 'pending' : 'subscribed',
            'status'        => $confirm ? 'pending' : 'subscribed',
            'merge_fields'  => $merge,
        ];
        $this->call('put', "/lists/{$listId}/members/{$memberId}", $data);
    }

    // HTTP -------------------------------------------------------------------

    public function call(string $method, string $endpoint, array $data = []): array
    {
        $method = strtolower($method);
        if (!in_array($method, ['get', 'put', 'post', 'delete', 'patch'])) {
            throw new MailchimpException('Invalid API call method: ' . $method);
        }
        if (in_array($method, ['get', 'delete'])) {
            $response = Requests::$method($this->baseurl . $endpoint, $this->headers(), $this->options());
        } else {
            $response = Requests::$method(
                $this->baseurl . $endpoint,
                $this->headers(),
                json_encode($data),
                $this->options()
            );
        }
        $this->responseCode = intval($response->status_code);
        if ($this->responseCode >= 400) {
            $this->apiError($response);
        }
        return json_decode($response->body, true);
    }

    protected function options(): array
    {
        return [
            'auth' => new Requests_Auth_Basic(['mcuser', $this->apikey]),
        ];
    }

    protected function headers(): array
    {
        return [];
    }

    protected function apiError(Requests_Response $response)
    {
        $info = var_export(json_decode($response->body, true), true);
        $message = "Mailchimp API error (" . $response->status_code . "): " . $info;
        if ($this->responseCode() <= 499) {
            throw new MailchimpBadRequestException($message);
        }
        throw new MailchimpInternalErrorException($message);
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
