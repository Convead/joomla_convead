<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Supports an HTML select list of files
 *
 * @since  11.1
 */
class JFormFieldStatuses extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Statuses';

	protected function getInput()
    {
        $enableVirtuemart = $this->enable('com_virtuemart');
        $enableHikashop = $this->enable('com_hikashop');
        $enableJShopping = $this->enable('com_jshopping');
//        echo '<pre>'; var_dump($enableVirtuemart, $enableHikashop, $enableJShopping);echo '</pre>'; die;
        if($enableVirtuemart){
            $virtOptions = $this->getVirtOptions();
        }

        if($enableHikashop){
            $hikaOptions = $this->getHikaOptions();
        }

        if($enableJShopping){
            $jsOptions = $this->getJsOptions();
        }

        $aStatuses = array(
                array('value' => 'new', 'name' => JText::_('PLG_CONVEAD_STATUS_NEW')),
                array('value' => 'paid', 'name' => JText::_('PLG_CONVEAD_STATUS_PAID')),
                array('value' => 'shipped', 'name' => JText::_('PLG_CONVEAD_STATUS_SHIPPED')),
                array('value' => 'cancelled', 'name' => JText::_('PLG_CONVEAD_STATUS_CANCELLED'))
        );

        ob_start();
        ?>
        <table class="adminTable">
            <thead>
                <th><?php echo JText::_('PLG_CONVEAD_STATUSES_CONVEAD')?></th>
                <?php if($enableVirtuemart) { ?>
                    <th>Virtuemart</th>
                <?php } ?>
                <?php if($enableHikashop) { ?>
                    <th>Hikashop</th>
                <?php } ?>
                <?php if($enableJShopping) { ?>
                    <th>JoomShopping</th>
                <?php } ?>
            </thead>
            <tbody>
                <?php foreach ($aStatuses as $s) { ?>
                    <tr>
                        <td><?php echo $s['name']; ?></td>
                    <?php if($enableVirtuemart) { ?>
                        <td>
                            <?php
                            $value = !empty($this->value['virtuemart'][$s['value']]) ? $this->value['virtuemart'][$s['value']] : '';
                            echo JHtml::_('select.genericlist', $virtOptions, $this->name.'[virtuemart]['.$s['value'].']', 'style="width: 150px;"', 'value', 'text', $value);
                            ?>
                        </td>
                    <?php } ?>
                    <?php if($enableHikashop) { ?>
                        <td>
                            <?php
                            $value = !empty($this->value['hikashop'][$s['value']]) ? $this->value['hikashop'][$s['value']] : '';
                            echo JHtml::_('select.genericlist', $hikaOptions, $this->name.'[hikashop]['.$s['value'].']', 'style="width: 150px;"', 'value', 'text', $value);
                            ?>
                        </td>
                    <?php } ?>
                    <?php if($enableJShopping) { ?>
                        <td>
                            <?php
                            $value = !empty($this->value['jshopping'][$s['value']]) ? $this->value['jshopping'][$s['value']] : '';
                            echo JHtml::_('select.genericlist', $jsOptions, $this->name.'[jshopping]['.$s['value'].']', 'style="width: 150px;"', 'value', 'text', $value);
                            ?>
                        </td>
                    <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();

        return $html;
    }

    private function enable($component){
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('COUNT(*)')
            ->from('#__extensions')
            ->where('element = '.$db->quote($component))
            ->where('type = '.$db->quote('component'))
            ->where('enabled = 1')
        ;
        $enable = $db->setQuery($query,0,1)->loadResult();
        return $enable;
    }

    private  function getVirtOptions(){
        $options = array();
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('order_status_code, order_status_name')
            ->from('#__virtuemart_orderstates')
            ->where('published = 1')
            ->order('ordering')
        ;
        $result = $db->setQuery($query)->loadObjectList();
        if(is_array($result) && count($result)){
            JFactory::getLanguage()->load('com_virtuemart_orders', JPATH_ROOT.'/components/com_virtuemart');
            foreach ($result as $item) {
                $options[] = JHtml::_('select.option', $item->order_status_code, JText::_($item->order_status_name));
            }
        }
        return $options;
    }

    private  function getHikaOptions(){
        require_once JPATH_ROOT . '/administrator/components/com_hikashop/helpers/helper.php';
        $options = array();
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('category_name')
            ->from('#__hikashop_category')
            ->where('category_type = '.$db->quote('status'))
            ->where('category_published = 1')
            ->where('category_parent_id != 1')
            ->order('category_ordering')
        ;
        $result = $db->setQuery($query)->loadObjectList();
        if(is_array($result) && count($result)){
            JFactory::getLanguage()->load('com_hikashop');
            foreach ($result as $item) {
                $options[] = JHtml::_('select.option', $item->category_name, hikashop_orderStatus($item->category_name));
            }
        }
        return $options;
    }


    private  function getJsOptions(){
        $options = array();
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('status_id, `name_ru-RU` AS name')
            ->from('#__jshopping_order_status')
        ;
        $result = $db->setQuery($query)->loadObjectList();

        if(is_array($result) && count($result)){
            foreach ($result as $item) {
                $options[] = JHtml::_('select.option', $item->status_id, $item->name);
            }
        }
        return $options;
    }
}
