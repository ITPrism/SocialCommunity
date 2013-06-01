<?php
/**
 * @package      SocialCommunity
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * SocialCommunity is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// no direct access
defined('_JEXEC') or die;
?>
<tr>
    <th width="1%">
        <input type="checkbox" name="checkall-toggle" value="" onclick="checkAll(this)" />
    </th>
	<th class="title" >
	     <?php echo JHtml::_('grid.sort',  'COM_SOCIALCOMMUNITY_NAME', 'a.name', $this->listDirn, $this->listOrder); ?>
	</th>
	<th width="10%">
	    <?php echo JText::_("COM_SOCIALCOMMUNITY_IMAGE"); ?>
	</th>
	<th width="10%">
	     <?php echo JHtml::_('grid.sort',  'COM_SOCIALCOMMUNITY_REGISTERED_DATE', 'b.registerDate', $this->listDirn, $this->listOrder); ?>
	</th>
	<th width="10%"><?php echo JText::_("COM_SOCIALCOMMUNITY_PROFILE"); ?></th>
    <th width="10%"><?php echo JText::_("COM_SOCIALCOMMUNITY_ENABLED"); ?></th>
    <th width="3%" class="nowrap"><?php echo JHtml::_('grid.sort',  'JGRID_HEADING_ID', 'a.id', $this->listDirn, $this->listOrder); ?></th>
</tr>
	  