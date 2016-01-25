<?php
namespace NZTim\Mailchimp;

use Exception;
use Illuminate\Log\Writer;
use Psr\Log\LoggerInterface;

class Mailchimp
{
    protected $drewMc;
    protected $error;
    /** @var Writer $logger */
    protected $logger;

    public function __construct(string $apikey, LoggerInterface $logger)
    {
        $this->drewMc = new DrewMMailchimp($apikey);
        $this->error = false;
        $this->logger = $logger;
    }

    /**
     * Checks the status of a list subscriber
     * Possible responses: 'subscribed', 'unsubscribed', 'cleaned', 'pending' or 'not found'
     * @param string $listId
     * @param string $emailAddress
     * @return string|false response or error
     */
    public function checkStatus(string $listId, string $emailAddress)
    {
        // Check the list exists
        if(!$this->checkListExists($listId)) {
            return false;
        }
        // Check whether the list has the subscriber
        $id = md5(strtolower($emailAddress));
        $endpoint = "lists/{$listId}/members/{$id}";
        $response = $this->callApi('get', $endpoint);
        if ($response === false) {
            return false;
        }
        if (empty($response['status'])) {
            return $this->errorResponse('Invalid response from Api - no status key: ' . var_export($response, true));
        }
        if ($response['status'] == 404) {
            $response['status'] = 'not found';
        }
        return $response['status'];
    }

    /**
     * Checks to see if an email address is subscribed to a list
     * Need to check the list exists first, because the response for non-existent list ID
     * and for a non-subscriber is the same
     * @param string $listId
     * @param string $emailAddress
     * @return bool
     */
    public function check(string $listId, string $emailAddress) : bool
    {
        $result = $this->checkStatus($listId, $emailAddress);
        if($result == 'subscribed' || $result == 'pending') {
            return true;
        }
        return false; // Includes error conditions
    }

    /**
     * False for no error, string for error message associated with most recent request
     * @return false|string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * @param string $listId
     * @param string $emailAddress
     * @param array $mergeFields
     * @param bool|false $confirm
     * @return bool
     */
    public function subscribe(string $listId, string $emailAddress, array $mergeFields = [], bool $confirm = false)
    {
        // Check the list exists
        if(!$this->checkListExists($listId)) {
            if ($this->error) {
                return false; // callApi() error
            }
            return $this->errorResponse('Subscribe called on list that does not exist: ' . $listId);
        }
        // Check address is valid for subscription
        $status = $this->checkStatus($listId, $emailAddress);
        if (in_array($status, ['subscribed', 'pending', 'cleaned'])) {
            $this->logger->warning("Attempt to subscribe {$emailAddress} to list#{$listId}, already on the list and marked {$status} - no action taken");
            return true;
        }
        // Add/update the subscriber - PUT does both
        $id = md5(strtolower($emailAddress));
        $endpoint = "lists/{$listId}/members/{$id}";
        $status = $confirm ? 'pending' : 'subscribed';
        $data = [
            'email_address' => $emailAddress,
            'status' => $status
        ];
        if(!empty($mergeFields)) {
            $data['merge_fields'] = $mergeFields;
        }
        $response = $this->callApi('put', $endpoint, $data);
        if (!$response) {
            return false;
        }
        if (empty($response['status']) || !in_array($response['status'], ['subscribed', 'pending'])) {
            return $this->errorResponse('Subscribe received unexpected response:' .  json_encode($response));
        }
        return true;
    }

    /**
     * @param string $listId
     * @return bool
     */
    protected function checkListExists($listId) : bool
    {
        $endpoint = "lists/{$listId}";
        $response = $this->callApi('get', $endpoint);
        if ($response === false) {
            return false; // API error
        }
        if (!empty($response['status']) && $response['status'] == 404) {
            return false; // Not found
        }
        if (empty($response['id'])) {
            return $this->errorResponse('Invalid response received by checkListExists');
        }
        return true;
    }

    // API methods

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data = []
     * @return array|false  Response or false for error
     */
    protected function callApi($method, $endpoint, $data = [])
    {
        $this->error = false;
        try {
            $response = $this->drewMc->$method($endpoint, $data);
        } catch (Exception $e) {
            return $this->errorResponse('Internal error (exception thrown): ' . $e->getMessage());
        }
        if ($response === false) {
            return $this->errorResponse('Internal error (empty result): ' . $this->drewMc->getLastError());
        }
        if (!empty($response['status']) && is_int($response['status']) && $response['status'] >= 400 && $response['status'] <= 599) {
            $message = 'Failed API call:' . var_export($response, true);
            return $this->errorResponse($message);
        }
        return $response;
    }

    protected function errorResponse($message) : bool
    {
        $this->error = $message;
        $this->logger->error($message);
        return false;
    }
}