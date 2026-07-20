<?php

/**
 * @version     1.0.6
 * @package     com_ra_members
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 15/06/26 CB send email
 * 23/06/26 CB Don't send blank report
 * 02/07/26 CB email report as table
 * 06/07/26 CB Renamed; apply subdomain filter on the API call, not after the fact
 */

namespace Ramblers\Component\Ra_delivery\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Ramblers\Component\Ra_delivery\Site\Service\Smtp2goActivityService;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class SmtpHelper {

    private const WATERMARK_RECORD_TYPE = 2;
    private const LOG_SUB_SYSTEM = 'RA Delivery';
    private const LOG_RECORD_TYPE = '1';
    private const INSERTED = 'inserted';
    private const DUPLICATE = 'duplicate';
    private const FAILED = 'failed';

    private $db;
    private $count = 0;
    private $email;
    private $error_count = 0;
    private $messages = array();
    private $notify_user = '';
    private $service;
    private $toolsHelper;

    public function __construct() {
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper();
        $this->service = new Smtp2goActivityService();
    }

    private function actionBounce($event) {
        $email = $event["recipient"];
        $details = '<tr>';
        $details = '<td>' . HTMLHelper::_('date', $event["date"], 'D d/m/y H:i') . '</td>';
        $reason = $event["event"];
        $details .= '<td>' . $reason . '</td>';
        $details .= '<td>' . $event['sender'] . '</td>';
        $details .= '<td>' . $email . '</td>';
        $details .= '<td>';
        $sql = 'SELECT u.id, u.name, p.preferred_name FROM #__users u ';
        $sql .= 'LEFT JOIN #__ra_profiles p ON u.id = p.id ';
        $sql .= 'WHERE u.email=' . $this->db->quote($email);
        $user = $this->toolsHelper->getItem($sql);
        if ($user) {
            $details .= ($user->preferred_name === '') ? $user->name : $user->preferred_name;
            if (($reason == 'hard-bounced ') || ($reason == 'rejected')) {
                $this->error_count++;
                $sql = 'UPDATE #__users SET block=1 WHERE id=' . (int) $user->id;
                $this->toolsHelper->executeCommand($sql);
                $details .= 'User blocked';
            }
        } else {
            $details .= 'No user found';
        }
        $details .= '</td>';
        $details = '</tr>';
        $this->email .= $details;
    }

    private function calculateStartDate($lookbackMinutes) {
        $watermark = $this->loadWatermark();

        if ($watermark === '') {
            return gmdate('Y-m-d\TH:i:s\Z', strtotime('-28 day'));
        }

        $timestamp = strtotime($watermark);

        if ($timestamp === false) {
            return gmdate('Y-m-d\TH:i:s\Z', strtotime('-28 day'));
        }

        return gmdate('Y-m-d\TH:i:s\Z', $timestamp - ($lookbackMinutes * 60));
    }

    private function filterEventsBySubdomain(array $events, $subdomainFilter) {
        if ($subdomainFilter === '') {
            return $events;
        }

        return array_values(array_filter($events, function ($event) use ($subdomainFilter) {
                    return $this->matchesSubdomainFilter($event, $subdomainFilter);
                }));
    }

    public function getMessages() {
        return $this->messages;
    }

    private function loadWatermark() {
        $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('key_value'))
                ->from($this->db->quoteName('#__ra_control'))
                ->where($this->db->quoteName('record_type') . ' = ' . self::WATERMARK_RECORD_TYPE);

        $this->db->setQuery($query);

        return trim((string) $this->db->loadResult());
    }

    private function logMessage($message, $ref = '0') {
        $this->toolsHelper->createLog(self::LOG_SUB_SYSTEM, self::LOG_RECORD_TYPE, $ref, $message);
    }

    private function matchesSubdomainFilter(array $event, $subdomainFilter) {
        if ($subdomainFilter === '') {
            return true;
        }

        $sender = strtolower(trim((string) ($event['sender'] ?? '')));

        return $sender !== '' && strpos($sender, $subdomainFilter) !== false;
    }

    private function normaliseConfiguredEventTypes($value) {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return array('soft-bounced', 'hard-bounced', 'rejected', 'spam');
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function normaliseEventDate($value) {
        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return gmdate('Y-m-d H:i:s');
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function normaliseSubdomainFilter($value) {
        return strtolower(trim((string) $value));
    }

    public function pollConfiguredEvents() {
        $params = ComponentHelper::getParams('com_ra_delivery');

        if ((int) $params->get('polling_enabled', 1) !== 1) {
            $this->messages[] = 'Polling is disabled in com_ra_delivery configuration';
            return array('inserted' => 0, 'duplicates' => 0, 'pages' => 0, 'events' => 0);
        }

        $apiSiteId = (int) $params->get('smtp2go_api_site_id', 0);

        if ($apiSiteId <= 0) {
            $this->messages[] = 'SMTP2GO API site id is not configured';
            return false;
        }

        $eventTypes = $this->normaliseConfiguredEventTypes($params->get('event_types'));
        $lookbackMinutes = max(0, (int) $params->get('lookback_minutes', 10));
        $pageLimit = min(1000, max(1, (int) $params->get('page_limit', 250)));
        $subdomainFilter = $this->normaliseSubdomainFilter($params->get('subdomain'));
        $tidyUpDays = max(0, (int) $params->get('tidy_up_days', 30));
        $this->notify_user = trim((string) $params->get('notify_user', ''));

        $startDate = $this->calculateStartDate($lookbackMinutes);
        $endDate = gmdate('Y-m-d\TH:i:s\Z');
        $continueToken = '';
        $stats = array('inserted' => 0, 'duplicates' => 0, 'failed' => 0, 'filtered' => 0, 'deleted' => 0, 'pages' => 0, 'events' => 0);
        $this->email = '';

        $this->messages[] = 'Polling SMTP2GO activity from ' . $startDate . ' to ' . $endDate;
        $this->logMessage('Starting activity poll from ' . $startDate . ' to ' . $endDate . ' for api site ' . $apiSiteId, (string) $apiSiteId);
        if ($this->notify_user == '') {
            $this->messages[] = 'No notification email address configured; no email will be sent';
        } else {
            $this->messages[] = 'Notification email will be sent to ' . $this->notify_user;
        }
        if ($subdomainFilter !== '') {
            $this->messages[] = 'Applying sender subdomain filter: ' . $subdomainFilter;
        }

        do {
            $subaccounts = ($subdomainFilter !== '') ? [$subdomainFilter] : [];
            $result = $this->service->searchActivity($apiSiteId, $startDate, $endDate, $eventTypes, $pageLimit, $continueToken, $subaccounts);

            if ($result === false) {
                $this->messages[] = 'Polling failed: ' . $this->service->getLastError();
                $this->logMessage('Polling failed: ' . $this->service->getLastError(), (string) $apiSiteId);
                return false;
            }

            $stats['pages']++;
            $events = $result['events'];
            $stats['events'] += count($events);
            $this->messages[] = 'Fetched page ' . $stats['pages'] . ' with ' . count($events) . ' events'
                    . ($result['request_id'] !== '' ? ' (request ' . $result['request_id'] . ')' : '');
            $this->logMessage(
                    'Fetched page ' . $stats['pages'] . ' with ' . count($events) . ' events'
                    . ($result['request_id'] !== '' ? ' (request ' . $result['request_id'] . ')' : ''),
                    (string) $apiSiteId
            );
            foreach ($events as $event) {
                if ($this->notify_user !== '') {
                    $this->actionBounce($event);
                }
                $storeResult = $this->storeEvent($apiSiteId, $event);

                if ($storeResult === self::INSERTED) {
                    $stats['inserted']++;
                } elseif ($storeResult === self::DUPLICATE) {
                    $stats['duplicates']++;
                } else {
                    $stats['failed']++;
                }
            }

            $continueToken = $result['continue_token'];
        } while ($continueToken !== '');

        if ($stats['failed'] > 0) {
            $this->messages[] = 'Polling completed with ' . $stats['failed'] . ' storage failures; watermark not advanced';
            $this->logMessage('Polling completed with ' . $stats['failed'] . ' storage failures; watermark not advanced', (string) $apiSiteId);
            return false;
        }
        if (($this->count > 0) AND ($this->notify_user !== '')) {
            $this->messages[] = 'Email sent to ' . $this->notify_user;
            $this->sendReport();
        }
        $this->storeWatermark($endDate);
        if ($tidyUpDays > 0) {
            $stats['deleted'] = $this->tidyUpOldEvents($tidyUpDays);
        }

        $this->messages[] = 'Polling complete: ' . $stats['inserted'] . ' inserted, ' . $stats['duplicates'] . ' duplicates'
                . ($stats['filtered'] > 0 ? ', ' . $stats['filtered'] . ' filtered out' : '')
                . ($stats['deleted'] > 0 ? ', ' . $stats['deleted'] . ' tidied up' : '');
        if ($this->error_count > 0) {
            $this->messages[] = $this->error_count . ' users were blocked due to hard bounces or rejections.';
        }
        $this->messages[] = 'Watermark updated to ' . $endDate;
        $this->logMessage(
                'Polling complete: ' . $stats['inserted'] . ' inserted, ' . $stats['duplicates'] . ' duplicates'
                . ($stats['filtered'] > 0 ? ', ' . $stats['filtered'] . ' filtered out' : '')
                . ($stats['deleted'] > 0 ? ', ' . $stats['deleted'] . ' tidied up' : '')
                . ', watermark updated to ' . $endDate,
                (string) $apiSiteId
        );

        return $stats;
    }

    public function sendEmail($to, $reply_to, $subject, $message, $attachments = '', $bcc = '') {
        $params = ComponentHelper::getParams('com_ra_delivery');
        $apiSiteId = (int) $params->get('smtp2go_api_site_id', 0);

        if ($apiSiteId <= 0) {
            $this->messages[] = 'SMTP2GO API site id is not configured';
            return false;
        }

        $payload = [
            'sender' => $reply_to,
            'to' => (array) $to,
            'subject' => $subject,
            'html_body' => $message,
            'fastaccept' => true,
        ];

        if (!empty($bcc)) {
            $payload['bcc'] = (array) $bcc;
        }

        if (!empty($attachments)) {
            // The documentation suggests attachments are objects with filename and fileblob
            // This part may need adjustment depending on the format of $attachments
        }
        
        if (!empty($reply_to)) {
            $payload['custom_headers'] = [
                [
                    'header' => 'Reply-To',
                    'value' => $reply_to
                ]
            ];
        }

        $result = $this->service->send($apiSiteId, $payload);

        if ($result === false) {
            $this->messages[] = 'Failed to send email: ' . $this->service->getLastError();
            $this->logMessage('Failed to send email: ' . $this->service->getLastError(), (string) $apiSiteId);
            return false;
        }

        $this->messages[] = 'Email sent successfully. Request ID: ' . ($result['request_id'] ?? 'N/A');
        return true;
    }

    private function sendReport() {
        $to = $this->notify_user;
        $reply_to = 'hyperbigley@gmail.com';
        $subject = 'SMTP2GO Activity Poll Report';

        $body = "The following activity events were detected during the latest poll<br>";
        $body .= '<table>';
        $body .= '<thead>';
        $body .= '<th>Date</th><th>Reason</th><th>Sender</th><th>Recipient</th><th>User</th>';
        $body .= '</thead>';
        $body .= '<tbody>';

        $body .= $this->email;
        $body .= '</tbody>';
        $body .= '</table>';
        if ($this->error_count > 0) {
            $body .= "<br><br>" . $this->error_count . " users were blocked due to hard bounces or rejections.";
        }
        $this->toolsHelper->sendEmail($to, $reply_to, $subject, $body);
    }

    private function storeEvent($apiSiteId, array $event) {
        $this->count++;
        $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__ra_delivery_events'))
                ->columns(array(
                    $this->db->quoteName('provider_name'),
                    $this->db->quoteName('api_site_id'),
                    $this->db->quoteName('email_id'),
                    $this->db->quoteName('event_type'),
                    $this->db->quoteName('event_date_utc'),
                    $this->db->quoteName('recipient'),
                    $this->db->quoteName('sender'),
                    $this->db->quoteName('subject'),
                    $this->db->quoteName('smtp_response'),
                    $this->db->quoteName('username'),
                    $this->db->quoteName('subaccount_name'),
                    $this->db->quoteName('host'),
                    $this->db->quoteName('outbound_ip'),
                    $this->db->quoteName('byte_size'),
                    $this->db->quoteName('raw_payload')
                ))
                ->values(implode(',', array(
            $this->db->quote('smtp2go'),
            (int) $apiSiteId,
            $this->db->quote((string) ($event['email_id'] ?? '')),
            $this->db->quote((string) ($event['event'] ?? '')),
            $this->db->quote($this->normaliseEventDate($event['date'] ?? '')),
            $this->db->quote((string) ($event['recipient'] ?? '')),
            $this->db->quote((string) ($event['sender'] ?? '')),
            $this->db->quote((string) ($event['subject'] ?? '')),
            $this->db->quote((string) ($event['smtp_response'] ?? '')),
            $this->db->quote((string) ($event['username'] ?? '')),
            $this->db->quote((string) ($event['subaccount_name'] ?? '')),
            $this->db->quote((string) ($event['host'] ?? '')),
            $this->db->quote((string) ($event['outbound_ip'] ?? '')),
            (int) ($event['byte_size'] ?? 0),
            $this->db->quote(json_encode($event))
        )));

        try {
            $this->db->setQuery($query);
            $this->db->execute();

            return self::INSERTED;
        } catch (\RuntimeException $exception) {
            if ((int) $exception->getCode() === 1062 || strpos($exception->getMessage(), 'Duplicate entry') !== false) {
                return self::DUPLICATE;
            }

            $this->messages[] = 'Failed to store event for email id '
                    . (string) ($event['email_id'] ?? '')
                    . ': ' . $exception->getMessage();
            $this->logMessage(
                    'Failed to store event for email id ' . (string) ($event['email_id'] ?? '') . ': ' . $exception->getMessage(),
                    (string) $apiSiteId
            );

            return self::FAILED;
        }
    }

    private function storeWatermark($value) {
        $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__ra_control'))
                ->columns(array($this->db->quoteName('record_type'), $this->db->quoteName('key_value')))
                ->values((int) self::WATERMARK_RECORD_TYPE . ',' . $this->db->quote($value));

        $sql = (string) $query . ' ON DUPLICATE KEY UPDATE ' . $this->db->quoteName('key_value') . ' = VALUES(' . $this->db->quoteName('key_value') . ')';
        $this->db->setQuery($sql);
        $this->db->execute();
    }

    public function testApiSite($apiSiteId) {
        $this->pollConfiguredEvents();
        return;

        $apiSiteId = (int) $apiSiteId;
        $params = ComponentHelper::getParams('com_ra_delivery');
        $eventTypes = $this->normaliseConfiguredEventTypes($params->get('event_types'));
        $lookbackMinutes = max(0, (int) $params->get('lookback_minutes', 10));
        $pageLimit = min(1000, max(1, (int) $params->get('page_limit', 250)));
        $subdomainFilter = $this->normaliseSubdomainFilter($params->get('subdomain'));

        if ($apiSiteId <= 0) {
            $this->messages[] = 'API site id is missing';
            return false;
        }

        $startDate = $this->calculateStartDate($lookbackMinutes);
        $endDate = gmdate('Y-m-d\TH:i:s\Z');
        $this->messages[] = 'Testing SMTP2GO activity for site ' . $apiSiteId . ' from ' . $startDate . ' to ' . $endDate;

        $subaccounts = ($subdomainFilter !== '') ? [$subdomainFilter] : [];
        $result = $this->service->searchActivity($apiSiteId, $startDate, $endDate, $eventTypes, $pageLimit, '', $subaccounts);

        if ($result === false) {
            $this->messages[] = 'Test failed: ' . $this->service->getLastError();
            return false;
        }

        $events = $result['events'];
        $this->messages[] = 'Request ' . ($result['request_id'] !== '' ? $result['request_id'] : 'n/a')
                . ' returned ' . count($events) . ' events';

        if ($subdomainFilter !== '') {
            $this->messages[] = 'Applied sender subdomain filter: ' . $subdomainFilter;
        }

        return array(
            'start_date' => $startDate,
            'end_date' => $endDate,
            'request_id' => (string) $result['request_id'],
            'event_count' => count($events),
            'events' => $events,
        );
    }

    private function tidyUpOldEvents($tidyUpDays) {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . (int) $tidyUpDays . ' days'));
        $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ra_delivery_events'))
                ->where($this->db->quoteName('event_date_utc') . ' < ' . $this->db->quote($cutoff));

        try {
            $this->db->setQuery($query);
            $this->db->execute();
            $deleted = (int) $this->db->getAffectedRows();
            $this->messages[] = 'Tidy-up removed ' . $deleted . ' rows older than ' . $tidyUpDays . ' days';
            return $deleted;
        } catch (\RuntimeException $exception) {
            $this->messages[] = 'Tidy-up failed: ' . $exception->getMessage();
            $this->logMessage('Tidy-up failed: ' . $exception->getMessage());
            return 0;
        }
    }

}
