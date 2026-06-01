<?php

namespace Ramblers\Component\Ra_delivery\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Ramblers\Component\Ra_delivery\Site\Service\Smtp2goActivityService;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class ActivityHelper
{
    private const WATERMARK_RECORD_TYPE = 2;

    private $db;
    private $messages = array();
    private $service;
    private $toolsHelper;

    public function __construct()
    {
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper();
        $this->service = new Smtp2goActivityService();
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function pollConfiguredEvents()
    {
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

        $startDate = $this->calculateStartDate($lookbackMinutes);
        $endDate = gmdate('Y-m-d\TH:i:s\Z');
        $continueToken = '';
        $stats = array('inserted' => 0, 'duplicates' => 0, 'pages' => 0, 'events' => 0);

        $this->messages[] = 'Polling SMTP2GO activity from ' . $startDate . ' to ' . $endDate;

        do {
            $result = $this->service->searchActivity($apiSiteId, $startDate, $endDate, $eventTypes, $pageLimit, $continueToken);

            if ($result === false) {
                $this->messages[] = 'Polling failed: ' . $this->service->getLastError();
                return false;
            }

            $stats['pages']++;
            $events = $result['events'];
            $stats['events'] += count($events);

            foreach ($events as $event) {
                if ($this->storeEvent($apiSiteId, $event)) {
                    $stats['inserted']++;
                } else {
                    $stats['duplicates']++;
                }
            }

            $continueToken = $result['continue_token'];
        } while ($continueToken !== '');

        $this->storeWatermark($endDate);
        $this->messages[] = 'Polling complete: ' . $stats['inserted'] . ' inserted, ' . $stats['duplicates'] . ' duplicates';

        return $stats;
    }

    private function calculateStartDate($lookbackMinutes)
    {
        $watermark = $this->loadWatermark();

        if ($watermark === '') {
            return gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 day'));
        }

        $timestamp = strtotime($watermark);

        if ($timestamp === false) {
            return gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 day'));
        }

        return gmdate('Y-m-d\TH:i:s\Z', $timestamp - ($lookbackMinutes * 60));
    }

    private function loadWatermark()
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('key_value'))
            ->from($this->db->quoteName('#__ra_control'))
            ->where($this->db->quoteName('record_type') . ' = ' . self::WATERMARK_RECORD_TYPE);

        $this->db->setQuery($query);

        return trim((string) $this->db->loadResult());
    }

    private function normaliseConfiguredEventTypes($value)
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return array('soft-bounced', 'hard-bounced', 'rejected', 'spam');
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function storeEvent($apiSiteId, array $event)
    {
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
            return true;
        } catch (\RuntimeException $exception) {
            return false;
        }
    }

    private function storeWatermark($value)
    {
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__ra_control'))
            ->columns(array($this->db->quoteName('record_type'), $this->db->quoteName('key_value')))
            ->values((int) self::WATERMARK_RECORD_TYPE . ',' . $this->db->quote($value));

        $sql = (string) $query . ' ON DUPLICATE KEY UPDATE ' . $this->db->quoteName('key_value') . ' = VALUES(' . $this->db->quoteName('key_value') . ')';
        $this->db->setQuery($sql);
        $this->db->execute();
    }

    private function normaliseEventDate($value)
    {
        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return gmdate('Y-m-d H:i:s');
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
