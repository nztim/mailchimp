<?php
namespace NZTim\Mailchimp;

use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class Mailchimp
{
    protected $apikey;
    protected $endpoint;
    protected $client;

    public function __construct($apikey, $datacenter)
    {
        $this->apikey = $apikey;
        $this->endpoint = "https://{$datacenter}.api.mailchimp.com/3.0/";
        $this->client = new Guzzle([
            'base_uri' => $this->endpoint,
            'timeout'  => 2.0,
            'auth' => ['nztim/mailchimp', $this->apikey],
            'exceptions' => false
        ]);
    }

    /**
     * Checks to see if an email address is subscribed to a list
     * Need to check the list exists first, because the response for non-existent list ID
     * and for a non-subscriber is the same
     * @param string $listId
     * @param string $emailAddress
     * @return bool
     * @throws MailchimpException
     */
    public function check($listId, $emailAddress)
    {
        $result = $this->checkStatus($listId, $emailAddress);
        if($result == 'subscribed') {
            return true;
        }
        return false;
    }

    /**
     * Checks the status of a list subscriber
     * Possible statuses: 'subscribed', 'unsubscribed', 'cleaned', 'pending', or 'not found'
     * @param $listId
     * @param $emailAddress
     * @return string
     * @throws MailchimpException
     */
    public function checkStatus($listId, $emailAddress)
    {
        // Check the list exists
        if(!$this->checkListExists($listId)) {
            throw $this->listDoesNotExistException($listId);
        }
        // Check whether the list has the subscriber
        $id = md5($emailAddress);
        $endpoint = "lists/{$listId}/members/{$id}";
        $response = $this->callApi('get', $endpoint);
        if($response->getStatusCode() == 200) {
            $details = json_decode($response->getBody()->getContents());
            return $details->status;
        }
        if($response->getStatusCode() == 404) {
            return 'not found';
        }
        throw $this->otherException($response);
    }

    /**
     * @param $listId
     * @return bool
     * @throws MailchimpException
     */
    protected function checkListExists($listId)
    {
        $endpoint = "lists/{$listId}";
        $response = $this->callApi('get', $endpoint);
        if($response->getStatusCode() == 200) {
            return true;
        }
        if($response->getStatusCode() == 404) {
            return false;
        }
        throw $this->otherException($response);
    }

    public function subscribe($listId, $emailAddress, $mergeFields = [], $confirm = false)
    {
        // Check the list exists
        if(!$this->checkListExists($listId)) {
            throw $this->listDoesNotExistException($listId);
        }
        // Check not already subscribed
        $subscribed = $this->check($listId, $emailAddress);
        if($subscribed) {
            return true;
        }
        // Add the subscriber
        $endpoint = "lists/{$listId}/members";
        $status = 'subscribed';
        if($confirm) {
            $status = 'pending';
        }
        $data = [
            'email_address' => $emailAddress,
            'status' => $status
        ];
        if(!empty($mergeFields)) {
            $data['merge_fields'] = $mergeFields;
        }
        $response = $this->callApi('post', $endpoint, $data);
        if($response->getStatusCode() == 200) {
            return true;
        }
        throw $this->otherException($response);
    }

    /**
     * @param $method
     * @param $endpoint
     * @param array $data
     * @return Response
     * @throws MailchimpException
     */
    protected function callApi($method, $endpoint, $data = [])
    {
        try {
            if($method == 'get') {
                $response = $this->client->get($endpoint);
            } else {
                $response = $this->client->$method($endpoint, ['body' => json_encode($data)]);
            }

        } catch (RequestException $e) {
            // All networking/comms errors caught here
            $message = $e->getMessage();
            $headers = $e->getRequest()->getHeaders();
            if(isset($headers['Authorization'])) {
                unset($headers['Authorization']);
            }
            $e = new MailchimpException;
            $e->setUserMessage('Mailchimp networking error: ' . $message . json_encode($headers));
            throw $e;
        }
        return $response;
    }

    protected function otherException(Response $response)
    {
        $e = new MailchimpException();
        if($response->getStatusCode() == 401) {
            $e->setUserMessage('401 Unauthorized - check API key');
            return $e;
        }
        $e->setUserMessage("Unknown response: {$response->getStatusCode()} {$response->getReasonPhrase()}");
        return $e;
    }

    protected function listDoesNotExistException($listId)
    {
        $e = new MailchimpException;
        $e->setUserMessage("List ID:{$listId} does not exist");
        return $e;
    }
}