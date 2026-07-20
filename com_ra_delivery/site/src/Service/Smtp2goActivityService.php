<?php

namespace Ramblers\Component\Ra_delivery\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class Smtp2goActivityService {

    private $db;
    private $lastError = '';
    private $toolsHelper;

    public function __construct() {
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper();
    }

    public function getLastError() {
        return $this->lastError;
    }

    /*
     * Returns the decoded json data, or false
     * Invoked on-line for testing, or from the batch process
     */

    public function searchActivity($apiSiteId, $startDate, $endDate, array $eventTypes, $limit, $continueToken = '', $subaccounts = []) {
        $site = $this->loadApiSite((int) $apiSiteId);

        if ($site === null) {
            $this->lastError = 'API site ' . $apiSiteId . ' not found';
            return false;
        }

        $payload = array(
            'start_date' => $startDate,
            'end_date' => $endDate,
            'event_types' => array_values($eventTypes),
            'limit' => (int) $limit,
            'only_latest' => false,
        );

        if ($continueToken !== '') {
            $payload['continue_token'] = $continueToken;
        }
        if (!empty($subaccounts)) {
            $payload['subaccounts'] = $subaccounts;
        }

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Smtp2go-Api-Key: ' . trim((string) $site->token),
        );

        $endpoint = rtrim((string) $site->url, '/') . '/v3/activity/search';
//        if (1) {
//            $endpoint .= '?subdomain=walsall';
//        }
        $response = $this->postJson($endpoint, $headers, $payload);

        if ($response === false) {
            return false;
        }

        return array(
            'events' => $response['data']['events'] ?? array(),
            'continue_token' => (string) ($response['data']['continue_token'] ?? ''),
            'request_id' => (string) ($response['request_id'] ?? ''),
        );
    }

    private function loadApiSite($apiSiteId) {
        $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ra_api_sites'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $apiSiteId);

        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    private function postJson($url, array $headers, array $payload) {
        $body = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($body === false) {
            $this->lastError = 'Failed to encode request payload as JSON: ' . json_last_error_msg();
            return false;
        }

        $max = 5 * 60;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'utf-8',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => $max,
            CURLOPT_TIMEOUT => $max,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $responseData = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($responseData === false || $httpCode !== 200) {
            $responseSummary = '';

            if (is_string($responseData) && trim($responseData) !== '') {
                $decodedError = json_decode($responseData, true);

                if (is_array($decodedError)) {
                    $requestId = (string) ($decodedError['request_id'] ?? '');
                    $errorMessage = (string) ($decodedError['data']['error'] ?? '');
                    $errorCode = (string) ($decodedError['data']['error_code'] ?? '');
                    $responseSummary = trim($requestId . ' ' . $errorCode . ' ' . $errorMessage);
                } else {
                    $responseSummary = trim(substr($responseData, 0, 300));
                }
            }

            $this->lastError = 'HTTP ' . $httpCode . ': ' . $error
                    . ($responseSummary !== '' ? ' | ' . $responseSummary : '');
            return false;
        }

        $decoded = json_decode($responseData, true);

        if (!is_array($decoded)) {
            $this->lastError = 'JSON decode failed';
            return false;
        }

        return $decoded;
    }

    public function send($apiSiteId, $payload) {
        $site = $this->loadApiSite((int) $apiSiteId);

        if ($site === null) {
            $this->lastError = 'API site ' . $apiSiteId . ' not found';
            return false;
        }

        $app = Factory::getApplication();
        $payload['sender'] = $app->get('mailfrom');

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Smtp2go-Api-Key: ' . trim((string) $site->token),
        );

        $endpoint = rtrim((string) $site->url, '/') . '/v3/email/send';
        $response = $this->postJson($endpoint, $headers, $payload);

        if ($response === false) {
            return false;
        }

        return $response;
    }

}
