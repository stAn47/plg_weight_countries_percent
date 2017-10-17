<?php
defined ('_JEXEC') or die('Restricted access');
/**
*
* @version $Id$
* @package VirtueMart
* @subpackage Shipment
* @author Stan Scholtz
* @copyright Copyright (C) 2017 by the RuposTel.com
* All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
*
* https://www.rupostel.com
*/
$method = $viewData['method']; 


		$dynUpdate='';
		if( VmConfig::get('oncheckout_ajax',false)) {
			
			$dynUpdate=' data-dynamic-update="1" ';
		}
		?><input type="radio" <?php 
		if( VmConfig::get('oncheckout_ajax',false)) {
			?> data-dynamic-update="1" <?php
		}
		?> name="virtuemart_shipmentmethod_id" id="shipment_id_<?php echo $method->virtuemart_shipmentmethod_id; ?>"   value="<?php echo $method->virtuemart_shipmentmethod_id; ?>" <?php echo $viewData['checked']; ?> >
			<label for="shipment_id_<?php echo $method->virtuemart_shipmentmethod_id; ?>">
			 <span class="vmshipment">
			  <?php 
			  /*render plugin name on your own: */

		
		
		
		if (!empty($method->shipment_logos)) {
			 echo $viewData['ref']->displayLogos ($method->shipment_logos);
		}
		
		?><span class="vmshipment_name"><?php echo $method->shipment_name;?></span><?php
		
		
		
if ($viewData['pluginSalesPrice']) {

			$t = vmText::_( 'COM_VIRTUEMART_PLUGIN_COST_DISPLAY' );
			if(strpos($t,'/')!==FALSE){
				list($discount, $fee) = explode( '/', vmText::_( 'COM_VIRTUEMART_PLUGIN_COST_DISPLAY' ) );
				if($viewData['pluginSalesPrice'] >=0 ) {
					?><span class="shipment_cost fee" style="display: inline-block;white-space: nowrap;"> (<?php echo $fee.' '.$viewData['costDisplay']; ?>)</span><?php
				} else if($viewData['pluginSalesPrice'] < 0 ) {
					?><span class="shipment_cost discount"> (<?php echo $discount.' -'.$viewData['costDisplay']; ?>)</span><?php
				}
			} else {
				?><span class="shipment_cost fee" style="display: inline-block;white-space: nowrap;"> (<?php echo vmText::_( 'COM_VIRTUEMART_PLUGIN_COST_DISPLAY' ).' +'.$viewData['costDisplay']; ?>)</span><?php
			}
		}
		
		if (!empty($method->shipment_desc)) {
			?><br /><div class="vmshipment_description"><?php echo $method->shipment_desc; ?></div><?php
		}
			 /*end render pugin name */
			  
			  
			 // echo $viewData['pluginNameValue'];
			  
			  $viewData['costDisplay']; 
			  ?></span>
			  </label>
<?php
