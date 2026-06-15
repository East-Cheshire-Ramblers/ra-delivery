<?php
/**
 * @version    1.0.0
 * @package    com_ra_delivery
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2026 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$user = Factory::getApplication()->getIdentity();
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
?>

<form action="<?php echo Route::_('index.php?option=com_ra_delivery&view=deliveryevents'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="deliveryeventList">
                    <thead>
                        <tr>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_PROVIDER_NAME', 'a.provider_name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_EVENT_DATE_UTC', 'a.event_date_utc', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_EVENT_TYPE', 'a.event_type', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_RECIPIENT', 'a.recipient', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_SENDER', 'a.sender', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_SUBJECT', 'a.subject', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DELIVERY_DELIVERYEVENTS_EMAIL_ID', 'a.email_id', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                            </th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php foreach ($this->items as $i => $item) :
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_ra_delivery&view=deliveryevent&id=' . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->provider_name); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo HTMLHelper::_('date', $item->event_date_utc, 'd M y H:i'); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->event_type); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->recipient); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->sender); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->subject); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->email_id); ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php echo $item->id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>