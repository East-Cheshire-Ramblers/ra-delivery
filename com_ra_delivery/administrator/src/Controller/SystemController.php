<?php

/**
 * @version     1.0.1
 * @package     com_ra_memebrs
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 25/04/26 CB Created
 */

namespace Ramblers\Component\Ra_delivery\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
//use Ramblers\Component\Ra_mailman\Site\Helpers\UserHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\UserHelper;

class SystemController extends FormController {

    protected $app;
    protected $back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
    protected $db;
    protected $toolsHelper;

    public function __construct(
            $config = [],
            MVCFactoryInterface $factory = null,
            CMSApplication $app = null,
            Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        $this->db = Factory::getDbo();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function checkSchema() {
//     administrator/index.php?option=com_ra_delivery&task=system.checkSchema
        /*
          CREATE TABLE IF NOT EXISTS `#__ra_control` (
          `record_type` INT NOT NULL,
          `key_value` VARCHAR(255) NOT NULL,
          PRIMARY KEY (`record_type`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
         */
        $toolsHelper = new ToolsHelper;
        if (!$toolsHelper->isSuperuser()) {
            return;
        }
        $helper = New SchemaHelper;
// table ra_control
        $details = '(
            `record_type` int NOT NULL,
            `key_value` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`record_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';
        $helper->checkTable('ra_control', $details);

        $helper->checkColumn('ra_api_sites', 'sub_system', 'U', 'VARCHAR(12) ');
        echo $this->toolsHelper->backButton($this->back);
    }

    function logMessage($record_type, $ref, $message) {
        $db = Factory::getDbo();

// Create a new query object.
        $query = $this->db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_logfile'))
                ->set('record_type =' . $db->quote($record_type))
                ->set('ref = ' . $db->quote($record_type))
                ->set('message =' . $db->quote($message));

// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
    }

    function test() {
        $toolsHelper = new ToolsHelper;
        $helper = New SchemaHelper;
        $helper->checkColumn('ra_logfile', 'sub_system', 'U', 'VARCHAR(12) NOT NULL; ');
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
//        return;

        $date = Factory::getDate();
        echo $date . '<br>';
    }

}
