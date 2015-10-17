<?php
namespace NZTim\Mailchimp;

use Exception;
use Log;

class Mailchimp
{
    protected $drewMc;

    public function __construct(DrewMMailchimp $drewMc)
    {
        $this->drewMc = $drewMc;
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
        if($result == 'subscribed' || $result == 'pending') {
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
            throw new MailchimpException('checkStatus called on a list that does not exist (' . $listId . ')');
        }
        // Check whether the list has the subscriber
        $id = md5(strtolower($emailAddress));
        $endpoint = "lists/{$listId}/members/{$id}";
        $response = $this->callApi('get', $endpoint);
        if (empty($response['status'])) {
            throw new MailchimpException('checkStatus return value did not contain status');
        }
        if ($response['status'] == 404) {
            $response['status'] = 'not found';
        }
        return $response['status'];
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
        if (!empty($response['status']) && $response['status'] == 404) {
            return false;
        }
        return true;
    }

    /**
     * @param integer $listId
     * @param string $emailAddress
     * @param array $mergeFields
     * @param bool|false $confirm
     * @throws MailchimpException
     */
    public function subscribe($listId, $emailAddress, $mergeFields = [], $confirm = false)
    {
        // Check the list exists
        if(!$this->checkListExists($listId)) {
            throw new MailchimpException('subscribe called on list that does not exist: ' . $listId);
        }
        // Check address is valid for subscription
        $status = $this->checkStatus($listId, $emailAddress);
        if (in_array($status, ['subscribed', 'pending', 'cleaned'])) {
            return;
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
        if (empty($response['status']) || !in_array($response['status'], ['subscribed', 'pending'])) {
            throw new MailchimpException('subscribe received unexpected response from DrewMMailchimp: ' . json_encode($response));
        }
    }


    /**
     * @param $method
     * @param $endpoint
     * @param array $data = []
     * @return array $response
     * @throws MailchimpException
     */
    protected function callApi($method, $endpoint, $data = [])
    {
        try {
            $response = $this->drewMc->$method($endpoint, $data);
        } catch (Exception $e) {
            throw new MailchimpException('DrewMMailchip exception: ' . $e->getMessage());
        }
        if ($response === false) {
            throw new MailchimpException('Error in DrewMMailchimp - possible connectivity problem');
        }
        return $response;
    }
}