<?php

/**
 * @version    1.1.6
 * @package    com_ra_delivery
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2026 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 15/06/26 CB date descending
 */

namespace Ramblers\Component\Ra_delivery\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_delivery\Administrator\Helper\Ra_deliveryHelper;
use \Joomla\Database\DatabaseInterface;

/**
 * Methods supporting a list of Deliveryevents records.
 *
 * @since  1.0.0
 */
class DeliveryeventsModel extends ListModel {

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'provider_name', 'a.provider_name',
                'api_site_id', 'a.api_site_id',
                'email_id', 'a.email_id',
                'event_type', 'a.event_type',
                'event_date_utc', 'a.event_date_utc',
                'recipient', 'a.recipient',
                'sender', 'a.sender',
                'subject', 'a.subject',
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('a.event_date_utc', 'DESC');

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        $eventType = $this->getUserStateFromRequest($this->context . '.filter.event_type', 'filter_event_type', '');
        $this->setState('filter.event_type', $eventType);

        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);

            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.event_type');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.0
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__ra_delivery_events` AS a');

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('('
                        . ' a.id LIKE ' . $search
                        . ' OR a.email_id LIKE ' . $search
                        . ' OR a.recipient LIKE ' . $search
                        . ' OR a.sender LIKE ' . $search
                        . ' OR a.subject LIKE ' . $search
                        . ' OR a.smtp_response LIKE ' . $search
                        . ' OR a.username LIKE ' . $search
                        . ' OR a.subaccount_name LIKE ' . $search
                        . ' OR a.host LIKE ' . $search
                        . ' OR a.provider_name LIKE ' . $search
                        . ' )');
            }
        }

        $eventType = $this->getState('filter.event_type');

        if ($eventType !== null && $eventType !== '') {
            $query->where('a.event_type = ' . $db->quote($eventType));
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        return $items;
    }

}
