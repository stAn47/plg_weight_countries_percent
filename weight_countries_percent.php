<?php
/**
 * Shipment plugin for weight_countries shipments, like regular postal services
 *
 * @version $Id: weight_countries.php 8635 2015-01-01 14:22:16Z Milbo $
 * @package VirtueMart
 * @subpackage Plugins - shipment
 * @copyright Copyright (C) 2004-2015 VirtueMart Team and Joomla Empresa Team - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 * http://www.joomlaempresa.es/en
 * @author Valerie Isaksen and José A. Cidre Bardelás
 *
 */

defined('_JEXEC') or die('Restricted access');

if(!class_exists('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS.DS.'vmpsplugin.php');
}

/**
 *
 */
class plgVmShipmentWeight_countries_percent extends vmPSPlugin {

	/**
	 * @param object $subject
	 * @param array  $config
	 */
	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);
		$this->_loggable = TRUE;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys($this->getTableSQLFields());
		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
		if (method_exists($this, 'setConvertable')) {
		$this->setConvertable(array('orderamount_start','orderamount_stop','shipment_cost','package_fee'));
		}
		
	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 * @author Valérie Isaksen
	 */
	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Shipment Weight Countries Table');
	}

	/**
	 * @return array
	 */
	function getTableSQLFields() {
		$SQLfields = array(
			'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(11) UNSIGNED',
			'order_number' => 'char(32)',
			'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
			'shipment_name' => 'varchar(5000)',
			'order_weight' => 'decimal(10,4)',
			'shipment_weight_unit' => 'char(3) DEFAULT \'KG\'',
			'shipment_cost' => 'decimal(10,2)',
			'shipment_package_fee' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)'
		);
		return $SQLfields;
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the shipment-specific data.
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The selected shipment method id
	 * @param string  $shipment_name Shipment Name
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valérie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
	}

	/**
	 * This event is fired after the order has been stored; it gets the shipment method-
	 * specific data.
	 *
	 * @param int    $order_id The order_id being processed
	 * @param object $cart  the cart
	 * @param array  $order The actual order saved in the DB
	 * @return mixed Null when this method was not selected, otherwise true
	 * @author Valerie Isaksen
	 */
	function plgVmConfirmedOrder(VirtueMartCart$cart, $order) {
		if(!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if(!$this->selectedThisElement($method->shipment_element)) {
			return FALSE;
		}
		$values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
		$values['order_number'] = $order['details']['BT']->order_number;
		$values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
		$values['shipment_name'] = $this->renderPluginName($method);
		$values['order_weight'] = $this->getOrderWeight($cart, $method->weight_unit);
		$values['shipment_weight_unit'] = $method->weight_unit;
		$costs = $this->getCosts($cart,$method,$cart->cartPrices);
		
		$values['shipment_cost'] = $costs;
		$values['shipment_package_fee'] = 0.0;
		
		
		$values['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData($values);
		return TRUE;
	}

	/**
	 * This method is fired when showing the order details in the backend.
	 * It displays the shipment-specific data.
	 * NOTE, this plugin should NOT be used to display form fields, since it's called outside
	 * a form! Use plgVmOnUpdateOrderBE() instead!
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The order shipment method ID
	 * @param object  $_shipInfo Object with the properties 'shipment' and 'name'
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderBEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id) {
		if(!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
			return NULL;
		}
		$html = $this->getOrderShipmentHtml($virtuemart_order_id);
		return $html;
	}

	/**
	 * @param $virtuemart_order_id
	 * @return string
	 */
	function getOrderShipmentHtml($virtuemart_order_id) {
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `'.$this->_tablename.'` '.'WHERE `virtuemart_order_id` = '.$virtuemart_order_id.' ORDER BY `id` DESC LIMIT 1'; 
		$db->setQuery($q);
		if(!($shipinfo = $db->loadObject())) {
			vmWarn(500, $q." ".$db->getErrorMsg());
			return '';
		}
		if(!class_exists('CurrencyDisplay')) {
			require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'currencydisplay.php');
		}
		$currency = CurrencyDisplay::getInstance();
		$tax = ShopFunctions::getTaxByID($shipinfo->tax_id);
		$taxDisplay = is_array($tax) ? $tax['calc_value'].' '.$tax['calc_value_mathop'] : $shipinfo->tax_id;
		$taxDisplay = ($taxDisplay == - 1) ? vmText::_('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;
		$html = '<table class="adminlist table">'."\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_SHIPPING_NAME', $shipinfo->shipment_name);
		$html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_WEIGHT', $shipinfo->order_weight.' '.ShopFunctions::renderWeightUnit($shipinfo->shipment_weight_unit));
		$html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_COST', $currency->priceDisplay($shipinfo->shipment_cost));
		//$html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_PACKAGE_FEE', $currency->priceDisplay($shipinfo->shipment_package_fee));
		$html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_TAX', $taxDisplay);
		$html .= '</table>'."\n";
		return $html;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $method
	 * @param                $cart_prices
	 * @return int
	 */
	function getCosts(VirtueMartCart$cart, $method, $cart_prices) {
		
		$this->convert($method); 
		
		$product_subtotal_with_tax = 0; 
		$product_subtotal_without_tax = 0; 
		foreach ($cart_prices as $key=>$data) {
			if (!is_numeric($key)) continue; 
			$quantity = floatval($cart->cartProductsData[$key]['quantity']); 
			
			if ((is_array($data)) && (isset($data['subtotal_with_tax']))) {
				$product_subtotal_with_tax += floatval($data['subtotal_with_tax']); 
			}elseif ((is_array($data)) && (isset($data['subtotal_with_tax']))) {
				$product_subtotal_with_tax += floatval($data['subtotal_with_tax']); 
			}
			if ((is_array($data)) && (isset($data['subtotal']))) {
				$product_subtotal_without_tax += floatval($data['subtotal']); 
			}
			elseif ((is_array($data)) && (isset($data['priceWithoutTax']))) {
				$product_subtotal_without_tax += (floatval($data['priceWithoutTax']) * $quantity); 
			}
			elseif ((is_array($data)) && (isset($data['priceBeforeTax']))) {
				$product_subtotal_without_tax += (floatval($data['priceBeforeTax']) * $quantity); 
			}
			
		}
		
		
		if((!empty($method->free_shipment)) && $cart_prices['salesPrice'] >= $method->free_shipment) {
			return 0.0;
		}
		else {
			if ($method->tax_handling === 0) {
			    return ($method->shipment_cost / 100 * $cart_prices['salesPrice']) + $method->package_fee;	
			}
			elseif (($method->tax_handling === 1) && (!empty($cart_prices['priceWithoutTax']))) {
				return ($method->shipment_cost / 100 * $cart_prices['priceWithoutTax']) + $method->package_fee;
			}
			elseif ($method->tax_handling === 2) {
				return ($method->shipment_cost / 100 * $product_subtotal_with_tax) + $method->package_fee;
			}
			elseif ($method->tax_handling === 3) {
				return ($method->shipment_cost / 100 * $product_subtotal_without_tax) + $method->package_fee;
			}
			
		}
		
		
		
		return 0.0;
	}

	/**
	 * @param \VirtueMartCart $cart
	 * @param int             $method
	 * @param array           $cart_prices
	 * @return bool
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		static $result = array();

		if($cart->STsameAsBT == 0){
			$type = ($cart->ST == 0 ) ? 'BT' : 'ST';


		} else {
			$type = 'BT';
		}

		$address = $cart -> getST();



		if(!is_array($address)) $address = array();
		if(isset($cart_prices['salesPrice'])){
			$hashSalesPrice = $cart_prices['salesPrice'];


		} else {
			$hashSalesPrice = '';
		}






		if(empty($address['virtuemart_country_id'])) $address['virtuemart_country_id'] = 0;
		if(empty($address['zip'])) $address['zip'] = 0;

		$hash = $method->virtuemart_shipmentmethod_id.$type.$address['virtuemart_country_id'].'_'.$address['zip'].'_'.$hashSalesPrice;

		if(isset($result[$hash])){
			return $result[$hash];
		}

		$this->convert ($method);

		if($this->_toConvert){
			$this->convertToVendorCurrency($method);
		}


		$orderWeight = $this->getOrderWeight ($cart, $method->weight_unit);

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;


			} else {
				$countries = $method->countries;
			}
		}


		$weight_cond = $this->testRange($orderWeight,$method,'weight_start','weight_stop','weight');
		$nbproducts_cond = $this->_nbproductsCond ($cart, $method);

		if(isset($cart_prices['salesPrice'])){
			$orderamount_cond = $this->testRange($cart_prices['salesPrice'],$method,'orderamount_start','orderamount_stop','order amount');


		} else {
			$orderamount_cond = FALSE;
		}

		$userFieldsModel =VmModel::getModel('Userfields');
		if ($userFieldsModel->fieldPublished('zip', $type)){
			if (!isset($address['zip'])) {
				$address['zip'] = '';
			}
			$zip_cond = $this->testRange($address['zip'],$method,'zip_start','zip_stop','zip');


		} else {
			$zip_cond = true;
		}

		if ($userFieldsModel->fieldPublished('virtuemart_country_id', $type)){

			if (!isset($address['virtuemart_country_id'])) {
				$address['virtuemart_country_id'] = 0;
			}

			if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {

				//vmdebug('checkConditions '.$method->shipment_name.' fit ',$weight_cond,(int)$zip_cond,$nbproducts_cond,$orderamount_cond);
				vmdebug('shipmentmethod '.$method->shipment_name.' = TRUE for variable virtuemart_country_id = '.$address['virtuemart_country_id'].', Reason: Countries in rule '.implode($countries,', ').' or none set');
				$country_cond = true;
			}
			else{
				vmdebug('shipmentmethod '.$method->shipment_name.' = FALSE for variable virtuemart_country_id = '.$address['virtuemart_country_id'].', Reason: Country '.implode($countries,', ').' does not fit');
				$country_cond = false;
			}


		} else {
			vmdebug('shipmentmethod '.$method->shipment_name.' = TRUE for variable virtuemart_country_id, Reason: no boundary conditions set');
			$country_cond = true;
		}

		$cat_cond = true;
		if($method->categories or $method->blocking_categories){
			if($method->categories)$cat_cond = false;
			//vmdebug('hmm, my value',$method);
			//if at least one product is  in a certain category, display this shipment
			if(!is_array($method->categories)) $method->categories = array($method->categories);
			if(!is_array($method->blocking_categories)) $method->blocking_categories = array($method->blocking_categories);
			//Gather used cats
			foreach($cart->products as $product){
				if(array_intersect($product->categories,$method->categories)){
					$cat_cond = true;
					//break;
				}
				if(array_intersect($product->categories,$method->blocking_categories)){
					$cat_cond = false;
					break;
				}
			}
			//if all products in a certain category, display the shipment
			//if a product has a certain category, DO NOT display the shipment
		}

		$allconditions = (int) $weight_cond + (int)$zip_cond + (int)$nbproducts_cond + (int)$orderamount_cond + (int)$country_cond + (int)$cat_cond;
		if($allconditions === 6){
			$result[$hash] = true;
			return TRUE;


		} else {
			$result[$hash] = false;

			//vmdebug('checkConditions '.$method->shipment_name.' does not fit ',(int)$weight_cond,(int)$zip_cond,(int)$nbproducts_cond,(int)$orderamount_cond,(int)$country_cond);
			return FALSE;
		}

		$result[$hash] = false;
		return FALSE;
	}

	/**
	 * @param $method
	 */
	function convert(&$method) {

		//$method->weight_start = (float) $method->weight_start;
		//$method->weight_stop = (float) $method->weight_stop;
		$method->orderamount_start = (float) str_replace(',', '.', $method->orderamount_start);
		$method->orderamount_stop = (float) str_replace(',', '.', $method->orderamount_stop);
		$method->zip_start = (int) $method->zip_start;
		$method->zip_stop = (int) $method->zip_stop;
		$method->nbproducts_start = (int) $method->nbproducts_start;
		$method->nbproducts_stop = (int) $method->nbproducts_stop;
		$method->free_shipment = (float) str_replace(',', '.', $method->free_shipment);
		if (empty($method->tax_handling)) $method->tax_handling = 0; 
		$method->tax_handling = (int)$method->tax_handling; 
		$method->shipment_cost = floatval($method->shipment_cost); 
		$method->package_fee = floatval($method->package_fee); 
	}

	/**
	 * @param $cart
	 * @param $method
	 * @return bool
	 */
	private function _nbproductsCond($cart, $method) {
		if(empty($method->nbproducts_start) and empty($method->nbproducts_stop)) {

			//vmdebug('_nbproductsCond',$method);
			return true;
		}
		$nbproducts = 0;
		foreach($cart->products as $product) {
			$nbproducts += $product->quantity;
		}
		if($nbproducts) {
			$nbproducts_cond = $this->testRange($nbproducts, $method, 'nbproducts_start', 'nbproducts_stop', 'products quantity');
		}
		else {
			$nbproducts_cond = false;
		}
		return $nbproducts_cond;
	}

	private function testRange($value, $method, $floor, $ceiling, $name) {
		$cond = true;
		if(!empty($method->$floor) and !empty($method->$ceiling)) {
			$cond = (($value >= $method->$floor AND $value <= $method->$ceiling));
			if(!$cond) {
				$result = 'FALSE';
				$reason = 'is NOT within Range of the condition from '.$method->$floor.' to '.$method->$ceiling;
			}
			else {
				$result = 'TRUE';
				$reason = 'is within Range of the condition from '.$method->$floor.' to '.$method->$ceiling;
			}
		}
		elseif(!empty($method->$floor)) {
			$cond = ($value >= $method->$floor);
			if(!$cond) {
				$result = 'FALSE';
				$reason = 'is not at least '.$method->$floor;
			}
			else {
				$result = 'TRUE';
				$reason = 'is over min limit '.$method->$floor;
			}
		}
		elseif(!empty($method->$ceiling)) {
			$cond = ($value <= $method->$ceiling);
			if(!$cond) {
				$result = 'FALSE';
				$reason = 'is over '.$method->$ceiling;
			}
			else {
				$result = 'TRUE';
				$reason = 'is lower than the set '.$method->$ceiling;
			}
		}
		else {
			$result = 'TRUE';
			$reason = 'no boundary conditions set';
		}
		vmdebug('shipmentmethod '.$method->shipment_name.' = '.$result.' for variable '.$name.' = '.$value.' Reason: '.$reason);
		return $cond;
	}

	function plgVmOnProductDisplayShipment($product, &$productDisplayShipments){

		if ($this->getPluginMethods($product->virtuemart_vendor_id) === 0) {

			return FALSE;
		}
		if (!class_exists('VirtueMartCart'))
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');



		$html = '';
		if (!class_exists('CurrencyDisplay'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		$currency = CurrencyDisplay::getInstance();



		foreach ($this->methods as $this->_currentMethod) {

			if($this->_currentMethod->show_on_pdetails){

				if(!isset($cart)){
					$cart = VirtueMartCart::getCart();
					$cart->products['virtual'] = $product;
					$cart->_productAdded = true;
					$cart->prepareCartData();
				}







				if($this->checkConditions($cart,$this->_currentMethod,$cart->cartPrices)){

					$product->prices['shipmentPrice'] = $this->getCosts($cart,$this->_currentMethod,$cart->cartPrices);

					if(isset($product->prices['VatTax']) and count($product->prices['VatTax'])>0){
						reset($product->prices['VatTax']);
						$rule = current($product->prices['VatTax']);
						if(isset($rule[1])){
							$product->prices['shipmentTax'] = $product->prices['shipmentPrice'] * $rule[1]/100.0;
							$product->prices['shipmentPrice'] = $product->prices['shipmentPrice'] * (1 + $rule[1]/100.0);
						}
					}

					$html[$this->_currentMethod->virtuemart_shipmentmethod_id] = $this->renderByLayout( 'default', array("method" => $this->_currentMethod, "cart" => $cart,"product" => $product,"currency" => $currency) );
				}
			}
		}
		if(isset($cart)){
			unset($cart->products['virtual']);
			$cart->_productAdded = true;
			$cart->prepareCartData();
		}


		$productDisplayShipments[] = $html;

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallShipmentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @return null
	 */
	public function plgVmOnSelectCheckShipment(VirtueMartCart&$cart) {
		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFE
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEShipment(VirtueMartCart$cart, $selected = 0, &$htmlIn) {
		if ($this->getPluginMethods ($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				vmAdminInfo ('displayListFE cartVendorId=' . $cart->vendorId);
				$app = JFactory::getApplication ();
				$app->enqueueMessage (vmText::_ ('COM_VIRTUEMART_CART_NO_' . strtoupper ($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}
		
		$mname = $this->_psType . '_name';
		$idN = 'virtuemart_'.$this->_psType.'method_id';

		$ret = FALSE;
		foreach ($this->methods as $method) {
			if(!isset($htmlIn[$this->_psType][$method->$idN])) {
				if ($this->checkConditions ($cart, $method, $cart->cartPrices)) {

					// the price must not be overwritten directly in the cart
					$prices = $cart->cartPrices;
					$methodSalesPrice = $this->setCartPrices ($cart, $prices ,$method);

					$htmlIn[$this->_psType][$method->$idN] = $this->getPluginHtml ($method, $selected, $methodSalesPrice);

					$ret = TRUE;
				}
			} else {
				$ret = TRUE;
			}
		}

		return $ret;
		
	}
	
	protected function getPluginHtml ($plugin, $selectedPlugin, $pluginSalesPrice) {

		$pluginmethod_id = $this->_idName;
		$pluginNameKey = $this->_psType . '_name';
		$pluginNameValue = $this->$pluginNameKey;
		if ($selectedPlugin == $plugin->$pluginmethod_id) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		
			if (!class_exists ('CurrencyDisplay')) {
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		}
		$currency = CurrencyDisplay::getInstance ();
		$costDisplay = "";
		if ($pluginSalesPrice) {
			$costDisplay = $currency->priceDisplay( $pluginSalesPrice );
		}
		
		
		$template_data = array(
		  'pluginmethod_id'=>$pluginmethod_id, 
		  'pluginNameKey'=>$pluginName, 
		  'pluginNameValue'=>$pluginNameValue, 
		  'method'=>$plugin,
		  'checked'=>$checked, 
		  'currency'=>$currency, 
		  'costDisplay'=>$costDisplay, 
		  'ref'=>$this, 
		  'pluginSalesPrice'=>$pluginSalesPrice,
		  
		  
		);
		
		
		
	
		$html = $this->renderByLayout( 'default_cart', $template_data);
		
		return $html;
	}
	
	
	/**
	 * @param VirtueMartCart $cart
	 * @param array          $cart_prices
	 * @param                $cart_prices_name
	 * @return bool|null
	 */
	public function plgVmOnSelectedCalculatePriceShipment(VirtueMartCart$cart, array&$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelected
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedShipment(VirtueMartCart$cart, array$cart_prices, &$shipCounter) {
		if($shipCounter > 1) {
			return 0;
		}
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $shipCounter);
	}
function plgVmOnCheckoutCheckDataShipment(VirtueMartCart $cart){

		if(empty($cart->virtuemart_shipmentmethod_id)) return false;

		$virtuemart_vendor_id = 1; //At the moment one, could make sense to use the cart vendor id
		if ($this->getPluginMethods($virtuemart_vendor_id) === 0) {
			return NULL;
		}

		foreach ($this->methods as $this->_currentMethod) {
			if($cart->virtuemart_shipmentmethod_id == $this->_currentMethod->virtuemart_shipmentmethod_id){
				if(!$this->checkConditions($cart,$this->_currentMethod,$cart->cartPrices)){
					return false;
				}
				break;
			}
		}
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrint($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsShipment($name, $id, &$dataOld) {
		return $this->declarePluginParams('shipment', $name, $id, $dataOld);
	}

	function plgVmDeclarePluginParamsShipmentVM3(&$data) {
		return $this->declarePluginParams('shipment', $data);
	}

	/**
	 * @author Max Milbers
	 * @param $data
	 * @param $table
	 * @return bool
	 */
	function plgVmSetOnTablePluginShipment(&$data, &$table) {
		$name = $data['shipment_element'];
		$id = $data['shipment_jplugin_id'];
		if(!empty($this->_psType) and !$this->selectedThis($this->_psType, $name, $id)) {
			return FALSE;
		}
		else {
			$toConvert = array(
				'weight_start',
				'weight_stop',
				'orderamount_start',
				'orderamount_stop',
				'shipment_cost',
				'package_fee'
				
			);
			foreach($toConvert as $field) {
				if(!empty($data[$field])) {
					$data[$field] = str_replace(array(',', ' '), array('.', ''), $data[$field]);
				}
				else {
					unset($data[$field]);
				}
			}
			$data['nbproducts_start'] = (int) $data['nbproducts_start'];
			$data['nbproducts_stop'] = (int) $data['nbproducts_stop'];

			//Reasonable tests:
			if(!empty($data['zip_start']) and !empty($data['zip_stop']) and (int) $data['zip_start'] >= (int) $data['zip_stop']) {
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_PERCENT_ZIP_CONDITION_WRONG');
			}
			if(!empty($data['weight_start']) and !empty($data['weight_stop']) and (float) $data['weight_start'] >= (float) $data['weight_stop']) {
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_PERCENT_WEIGHT_CONDITION_WRONG');
			}
			if(!empty($data['orderamount_start']) and !empty($data['orderamount_stop']) and (float) $data['orderamount_start'] >= (float) $data['orderamount_stop']) {
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_PERCENT_AMOUNT_CONDITION_WRONG');
			}
			if(!empty($data['nbproducts_start']) and !empty($data['nbproducts_stop']) and (float) $data['nbproducts_start'] >= (float) $data['nbproducts_stop']) {
				vmWarn('VMSHIPMENT_WEIGHT_COUNTRIES_PERCENT_NBPRODUCTS_CONDITION_WRONG');
			}
			return $this->setOnTablePluginParams($name, $id, $table);
		}
	}
}

// No closing tag
