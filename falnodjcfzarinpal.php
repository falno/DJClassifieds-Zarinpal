<?php
ini_set('display_errors', 1);
defined('_JEXEC') or die('Restricted access');
jimport('joomla.event.plugin');
$lang = JFactory::getLanguage();
$lang->load('plg_djclassifiedspayment_falnodjcfzarinpal',JPATH_ADMINISTRATOR);
require_once(JPATH_BASE.DS.'administrator/components/com_djclassifieds/lib/djseo.php');
require_once(JPATH_BASE.DS.'administrator/components/com_djclassifieds/lib/djnotify.php');


class plgdjclassifiedspaymentfalnodjcfzarinpal extends JPlugin
{
	// constructor
	function plgdjclassifiedspaymentfalnodjcfzarinpal( &$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage('plg_falnodjcfzarinpal');
		$params["plugin_name"] = "djcfZarinpal";
		$params["icon"] = "zarinpal_icon.png";		$params["logo"] = "zarinpal_overview.png";		$params["title"] = $this->params->get("title");		$params["description"] = $this->params->get("description");
		$params["payment_method"] = 'Zarinpal';
		$params["test"] = $this->params->get("test");
		$params["currency_code"] = $this->params->get("currency_code");
		$params["merchant_id"] = $this->params->get("merchant_id");
		$params["gate_type"] = $this->params->get("gate_type");
		$this->params = $params;

	}

	/*
	 * called when go for payment
	 */
	function onProcessPayment()
	{
		$ptype = JRequest::getVar('ptype','');
		$id = JRequest::getInt('id','0');
		$html="";

		if($ptype == $this->params["plugin_name"])
		{
			$action = JRequest::getVar('pactiontype','');
			switch ($action)
			{
				case "notify" :
					$html = $this->_notify_url();
					break;
				case 'process':
				default :
					$html =  $this->process($id);
					break;
			}
		}
		return $html;
	}

	/*
	 * calling when payment submit clicked
	 */
	function process($id)
	{
		JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');		
		jimport( 'joomla.database.table' );
		$db 	= JFactory::getDBO();
		$app 	= JFactory::getApplication();
		$par 	= JComponentHelper::getParams( 'com_djclassifieds' );
		$user 	= JFactory::getUser();
		$ptype	= JRequest::getVar('ptype'); // payment plaugin type
		$type	= JRequest::getVar('type','');
		$row 	= JTable::getInstance('Payments', 'DJClassifiedsTable');

		$remote_addr =  $_SERVER['REMOTE_ADDR'];

		if($type=='prom_top'){
			$query ="SELECT i.* FROM #__djcf_items i "
				."WHERE i.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$item = $db->loadObject();
			if(!isset($item)){
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
				$app->redirect(JRoute::_($redirect), $message, 'warning');
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $remote_addr;
			$row->price = $par->get('promotion_move_top_price',0);
			$row->type=2;
			$row->store();

			$amount = $par->get('promotion_move_top_price',0);
			$itemname = $item->name;
			$payment_id = $row->id;
			$item_cid = '&cid='.$item->cat_id;
		}else if($type=='points'){
			$query ="SELECT p.* FROM #__djcf_points p "
				."WHERE p.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$points = $db->loadObject();
			if(!isset($points)){
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_POINTS_PACKAGE');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
				$app->redirect(JRoute::_($redirect), $message, 'warning');
			}
			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $remote_addr;
			$row->price = $points->price;
			$row->type=1;

			$row->store();

			$amount = $points->price;
			$itemname = $points->name;
			$payment_id = $row->id;
			$item_cid = '';
		}else{
			$query ="SELECT i.*, c.price as c_price FROM #__djcf_items i "
				."LEFT JOIN #__djcf_categories c ON c.id=i.cat_id "
				."WHERE i.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$item = $db->loadObject();

			if(!isset($item)){
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
				$app->redirect(JRoute::_($redirect), $message, 'warning');
			}

			$amount = 0;

			if(strstr($item->pay_type, 'cat')){
				$amount += $item->c_price/100;
			}
			if(strstr($item->pay_type, 'duration_renew')){
				$query = "SELECT d.price_renew FROM #__djcf_days d "
					."WHERE d.days=".$item->exp_days;
				$db->setQuery($query);
				$amount += $db->loadResult();
			}else if(strstr($item->pay_type, 'duration')){
				$query = "SELECT d.price FROM #__djcf_days d "
					."WHERE d.days=".$item->exp_days;
				$db->setQuery($query);
				$amount += $db->loadResult();
			}

			$query = "SELECT p.* FROM #__djcf_promotions p "
				."WHERE p.published=1 ORDER BY p.id ";
			$db->setQuery($query);
			$promotions=$db->loadObjectList();
			foreach($promotions as $prom){
				if(strstr($item->pay_type, $prom->name)){
					$amount += $prom->price;
				}
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $remote_addr;
			$row->price = $amount;
			$row->type=0;

			$row->store();

			$itemname = $item->name;
			$payment_id = $row->id;
			$item_cid = '&cid='.$item->cat_id;
		}

		//TODO: request to zarinpal
		$MerchantID = $this->params['merchant_id'];
		$Amount = $this->zarinPalAmount($amount);

		$payment_title = 'ItemID:'.$id.' ('.$itemname.')';
		$payment_reason = $type ? $type : $item->pay_type;

		$Description = $payment_title.' - '.$payment_reason;
		$Mobile = '';
		$Email = empty($user->email) ? '' : $user->email;
		$CallbackURL = JRoute::_(JURI::base() . 'index.php?option=com_djclassifieds&task=processPayment&ptype=djcfZarinpal&pactiontype=notify&id='.$payment_id. '&amount=' . $Amount);

		$requestContext = compact(
			'MerchantID',
			'Amount',
			'Description',
			'Email',
			'Mobile',
			'CallbackURL'
		);

		try{
			$request = $this->zarinPalRequest('request', $requestContext);
			if(!$request)
				throw new Exception( JText::_("PLG_DJCFZARINPAL_PAYMENT_CONNECTION_FAILED"));

			$status = $request->Status;
			if($status == 100)
			{
				$authority = $request->Authority;
				//TODO: check test mode
				$isTest = (bool)($this->params['test']);
				$prefix = $isTest ? 'sandbox' : 'www';
				$postfix = (bool)($this->params['gate_type']) ? '' : '/ZarinGate';
				$zarinpalUrl = "https://{$prefix}.zarinpal.com/pg/StartPay/{$authority}{$postfix}";
				//TODO: redirect to zarinpal
				$app->redirect($zarinpalUrl);
			}

			throw new Exception($this->zarinPalStatusMessage($status));

		} catch (Exception $e) {
			$return = JRoute::_('index.php/component/djclassifieds/?view=payment&id=' . $id, false);
			$message = JText::_("PLG_DJCFZARINPAL_PAYMENT_ERROR") . $e->getMessage();
			$app->redirect($return, $message, 'error');
			exit;
		}
	}

	/*
 * called when back from zarinpal
 */
	function _notify_url()
	{
		$db = JFactory::getDBO();
		$par = &JComponentHelper::getParams( 'com_djclassifieds' );
		// $user	= JFactory::getUser();
		$payment_id	= JRequest::getInt('id', 0);
		$app = JFactory::getApplication();
		$input = $app->input;
		$messageUrl = JRoute::_(DJClassifiedsSEO::getCategoryRoute('0:all'));

		try{
			if($input->getString('Status') != 'OK')
				throw new Exception( JText::_("PLG_DJCFZARINPAL_PAYMENT_FAILED"));

			$Authority = $input->getString('Authority');
			$MerchantID = $this->params['merchant_id'];
			$Amount = $input->getInt('amount');

			$verifyContext = compact('MerchantID', 'Authority', 'Amount');

			$verify = $this->zarinPalRequest('verification', $verifyContext);

			if(!$verify)
				throw new Exception( JText::_("PLG_DJCFZARINPAL_PAYMENT_CONNECTION_FAILED"));

			$status = $verify->Status;

			if($status == 100){
				$RefID = $verify->RefID;

				$query = "UPDATE #__djcf_payments SET status='Completed', transaction_id='".$RefID."' "
					."WHERE id=".$payment_id." AND method='".$this->params['plugin_name']."'";
				$db->setQuery($query);
				$db->query();

				$this->_setPaymentCompleted((int)$payment_id);

				$message = JText::_("PLG_DJCFZARINPAL_PAYMENT_SUCCEED") . '<br>' .  JText::_("PLG_DJCFZARINPAL_PAYMENT_REF_ID") . $RefID;
				$app->redirect($messageUrl, $message, 'message');

				exit;
			}

			throw new Exception($this->zarinPalStatusMessage($status));

		} catch (Exception $e) {
			$message = JText::_("PLG_DJCFZARINPAL_PAYMENT_ERROR") . $e->getMessage();
			$app->redirect($messageUrl, $message, 'warning');
			exit;
		}
	}

	/*
	 * set payment completed
	 */
	private function _setPaymentCompleted($id) {

		$db = JFactory::getDBO();
		$par 	= JComponentHelper::getParams( 'com_djclassifieds' );

		$query = "SELECT p.*  FROM #__djcf_payments p "
			."WHERE p.id='".$id."' ";
		$db->setQuery($query);
		$payment = $db->loadObject();

		if($payment){

			if($payment->type==2){

				$date_sort = date("Y-m-d H:i:s");
				$query = "UPDATE #__djcf_items SET date_sort='".$date_sort."' "
					."WHERE id=".$payment->item_id." ";
				$db->setQuery($query);
				$db->query();
			}else if($payment->type==1){

				$query = "SELECT p.points  FROM #__djcf_points p WHERE p.id='".$payment->item_id."' ";
				$db->setQuery($query);
				$points = $db->loadResult();

				$query = "INSERT INTO #__djcf_users_points (`user_id`,`points`,`description`) "
					."VALUES ('".$payment->user_id."','".$points."','".JText::_('COM_DJCLASSIFIEDS_POINTS_PACKAGE')." - ".$this->params['payment_method']." <br />".JText::_('COM_DJCLASSIFIEDS_PAYMENT_ID').': '.$payment->id."')";
				$db->setQuery($query);
				$db->query();
			}else{

				$query = "SELECT c.*  FROM #__djcf_items i, #__djcf_categories c "
					."WHERE i.cat_id=c.id AND i.id='".$payment->item_id."' ";
				$db->setQuery($query);
				$cat = $db->loadObject();

				$pub=0;
				if(($cat->autopublish=='1') || ($cat->autopublish=='0' && $par->get('autopublish')=='1')){
					$pub = 1;
				}

				$query = "UPDATE #__djcf_items SET payed=1, pay_type='', published='".$pub."' "
					."WHERE id=".$payment->item_id." ";
				$db->setQuery($query);
				$db->query();
			}

		}

	}


	private function zarinPalAmount($amount)
	{
		$currency = $this->params['currency_code'];
		if(!(bool)$currency){// currency_code == 0 => rial
			$amount = $amount / 10;
		}
		return (int)$amount;
	}

	/*
	 * make a zarinpal soap request
	 */
	private function zarinPalRequest($type, $context)
	{
		try {
			$prefix =  (bool)$this->params['test'] ? 'sandbox' : 'www';
			$client = new SoapClient("https://{$prefix}.zarinpal.com/pg/services/WebGate/wsdl", array('encoding' => 'UTF-8'));

			$type = 'Payment' . ucfirst($type);
			$result = $client->$type($context);
			return $result;
		} catch (SoapFault $e) {
			return false;
		}
	}

	/*
	 * return related zarinpal status message
	 */
	private function zarinPalStatusMessage($status)
	{
		$prefix = "PLG_DJCFZARINPAL_PAYMENT_STATUS_";
		$status = $prefix . $status;
		$message =  JText::_($status);
		if ($message == $status) {
			return JText::_($prefix . 'UNDEFINED');
		}
		return $message;
	}

	/*
	 * when payment will be listed in payment choose page
	 */
	function onPaymentMethodList($val)
	{
		$type='';
		if($val['type']){
			$type='&type='.$val['type'];	
		}		
		$html ='';
		if($this->params["merchant_id"] != ''){
			$payText =  JText::_("FALNO_BUY_NOW");
			$paymentLogoPath = JURI::root()."plugins/djclassifiedspayment/falnodjcfzarinpal/tmp/".$this->params["logo"];
			$form_action = JURI::root()."index.php?option=com_djclassifieds&task=processPayment&ptype=".$this->params["plugin_name"]."&pactiontype=process&id=".$val["id"].$type;
			$html ='<table cellpadding="5" cellspacing="0" width="100%" border="0">
				<tr>';
					if($this->params["logo"] != ""){
				$html .='<td class="td1" width="160" align="center">
						<img src="'.$paymentLogoPath.'" title="'. $this->params["payment_method"].'"/>
					</td>';
					 }
					$html .='<td class="td2">
						<h2 style="font-family: tahoma;font-size: 12px;">'.$this->params["title"].'</h2>
						<p style="text-align:justify;font-family: tahoma;font-size: 12px;">'.$this->params["description"].'</p>
					</td>
					<td class="td3" width="130" align="center">
						<a class="button" style="text-decoration:none;" href="'.$form_action.'">'.JText::_('FALNO_BUY_NOW').'</a>
					</td>
				</tr>
			</table>';
		}
		return $html;
	}
}
