<?php
require_once('Litle/LitleSDK/LitleOnline.php');

class Litle_CreditCard_Model_PaymentLogic extends Mage_Payment_Model_Method_Cc
{
	/**
	 * unique internal payment method identifier
	 */
	protected $_code = 'creditcard';

	protected $_formBlockType = 'creditcard/form_creditCard';
	/**
	 * this should probably be true if you're using this
	 * method to take payments
	 */
	protected $_isGateway               = true;

	/**
	 * can this method authorise?
	 */
	protected $_canAuthorize            = true;

	/**
	 * can this method capture funds?
	 */
	protected $_canCapture              = true;

	/**
	 * can we capture only partial amounts?
	 */
	protected $_canCapturePartial       = true;

	/**
	 * can this method refund?
	 */
	protected $_canRefund               = true;

	protected $_canRefundInvoicePartial		= true;

	/**
	 * can this method void transactions?
	 */
	protected $_canVoid                 = true;

	/**
	 * can admins use this payment method?
	 */
	protected $_canUseInternal          = true;

	/**
	 * show this method on the checkout page
	 */
	protected $_canUseCheckout          = true;

	/**
	 * available for multi shipping checkouts?
	 */
	protected $_canUseForMultishipping  = true;

	/**
	 * can this method save cc info for later use?
	 */
	protected $_canSaveCc = false;

	public function getConfigData($fieldToLookFor, $store = NULL)
	{
		$returnFromThisModel = Mage::getStoreConfig('payment/CreditCard/' . $fieldToLookFor);
		if( $returnFromThisModel == NULL )
		$returnFromThisModel = parent::getConfigData($fieldToLookFor, $store);

		return $returnFromThisModel;
	}

	public function isFromVT($payment, $txnType)
	{
		$parentTxnId = $payment->getParentTransactionId();
		if( $parentTxnId == "Litle VT" )
		{
			Mage::throwException("This order was placed using Litle Virtual Terminal. Please process the $txnType by logging into Litle Virtual Terminal (https://vt.litle.com).");
		}
	}

	public function assignData($data)
	{
		if( $this->getConfigData('paypage_enabled') == "1")
		{
			if (!($data instanceof Varien_Object)) {
				$data = new Varien_Object($data);
			}
				
			$info = $this->getInfoInstance();
			$info->setAdditionalInformation('paypage_enabled', $data->getPaypageEnabled());
			$info->setAdditionalInformation('paypage_registration_id', $data->getPaypageRegistrationId());
			$info->setAdditionalInformation('paypage_order_id', $data->getOrderId());
		}
		return parent::assignData($data);
	}



	public function validate()
	{
		//no cc validation required.
		return $this;
	}

	public function litleCcTypeEnum(Varien_Object $payment)
	{
		$typeEnum = "";
		if ($payment->getCcType() == "AE"){
			$typeEnum = "AX";
		}
		elseif ($payment->getCcType() == "JCB"){
			$typeEnum = "JC";
		}
		else{
			$typeEnum =$payment->getCcType();
		}
		return $typeEnum;
	}

	public function getCreditCardInfo(Varien_Object $payment)
	{
		$retArray = array();
		$retArray["type"] = $this->litleCcTypeEnum($payment);
		$retArray["number"] = $payment->getCcNumber();
		preg_match("/\d\d(\d\d)/", $payment->getCcExpYear(), $expYear);
		$retArray["expDate"] = sprintf('%02d%02d', $payment->getCcExpMonth(), $expYear[1]);
		$retArray["cardValidationNum"] = $payment->getCcCid();

		return $retArray;
	}

	public function getPaypageInfo($payment)
	{
		$info = $this->getInfoInstance();

		$retArray = array();
		$retArray["type"] = $this->litleCcTypeEnum($payment);
		$retArray["paypageRegistrationId"] = $info->getAdditionalInformation('paypage_registration_id');
		preg_match("/\d\d(\d\d)/", $payment->getCcExpYear(), $expYear);
		$retArray["expDate"] = sprintf('%02d%02d', $payment->getCcExpMonth(), $expYear[1]);
		$retArray["cardValidationNum"] = $payment->getCcCid();

		return $retArray;
	}

	public function creditCardOrPaypage($payment){
		$info = $this->getInfoInstance();
		$payment_hash = array();
		//Mage::throwException($info->getAdditionalInformation('paypage_registration_id'));
		if ($info->getAdditionalInformation('paypage_enabled') == "1" ){
			$payment_hash['paypage'] = $this->getPaypageInfo($payment);
		}
		else{
			$payment_hash['card'] = $this->getCreditCardInfo($payment);
		}
		return $payment_hash;
	}

	public function getContactInformation($contactInfo)
	{
		if(!empty($contactInfo)){
			$retArray = array();
			$retArray["firstName"] =$contactInfo->getFirstname();
			$retArray["lastName"] = $contactInfo->getLastname();
			$retArray["companyName"] = $contactInfo->getCompany();
			$retArray["addressLine1"] = $contactInfo->getStreet(1);
			$retArray["addressLine2"] = $contactInfo->getStreet(2);
			$retArray["addressLine3"] = $contactInfo->getStreet(3);
			$retArray["city"] = $contactInfo->getCity();
			$retArray["state"] = $contactInfo->getRegion();
			$retArray["zip"] = $contactInfo->getPostcode();
			$retArray["country"] = $contactInfo->getCountry();
			$retArray["email"] = $contactInfo->getCustomerEmail();
			$retArray["phone"] = $contactInfo->getTelephone();
			return $retArray;
		}
		return NULL;
	}


	public function getBillToAddress(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		if(!empty($order)){
			$billing = $order ->getBillingAddress();
			if(!empty($billing)){
				return $this->getContactInformation($billing);
			}
		}
		return NULL;
	}

	public function getShipToAddress(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		if(!empty($order)){
			$shipping = $order->getShippingAddress();
			if(!empty($shipping)){
				return $this->getContactInformation($shipping);
			}
		}
		return NULL;
	}


	public function getIpAddress(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		if(!empty($order)){
			return $order->getRemoteIp();
		}
		return NULL;
	}



	public function getMerchantId(Varien_Object $payment){
		$order = $payment->getOrder();
		$currency = $order->getOrderCurrencyCode();
		$string2Eval = 'return array' . $this->getConfigData("merchant_id") . ';';
		$merchant_map = eval($string2Eval);
		$merchantId = $merchant_map[$currency];
		//Mage::throwException($merchantId);
		return $merchantId;
	}


	public function merchantData(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		$hash = array('user'=> $this->getConfigData("user"),
 					'password'=> $this->getConfigData("password"),
					'merchantId'=> $this->getMerchantId($payment),
					'version'=>'8.10',
					'merchantSdk'=>'Magento;8.12.1-pre',
					'reportGroup'=>$this->getMerchantId($payment),
					'customerId'=> $order->getCustomerEmail(),
					'url'=>$this->getConfigData("url"),	
					'proxy'=>$this->getConfigData("proxy"),
					'timeout'=>$this->getConfigData("timeout")
		);
		return $hash;
	}
	
	public function getCustomBilling(){
		$retArray = array();
		$retArray['url'] = Mage::app()->getStore()-> getBaseUrl();
		return $retArray;
		//Mage::app()->getStore()->getName(); //gets store name
	}
	
	public function getLineItemData(Varien_Object $payment){
			$order = $payment->getOrder();
			$items = $order->getAllItems();
			$i = 0;
			$lineItemArray = array();
			foreach ($items as $itemId => $item)
			{
				$name[$i] = $item->getName();
				$unitPrice[$i]=$item->getPrice();
				$sku[$i]=$item->getSku();
				$ids[$i]=$item->getProductId();
				$qty[$i]=$item->getQtyToInvoice();
 				$lineItemArray[$i] = array('itemSequenceNumber'=>$i,'itemDescription'=>'desc','productCode'=>$ids[$i],'quantity'=>$qty[$i]);
				$i++;
			}
			return $lineItemArray;
		}


	public function getEnhancedData(Varien_Object $payment)
	{
		$order = $payment->getOrder();


		$billing = $order->getBillingAddress();
		$i = 0;
		$hash = array('salesTax'=> $order->getTaxAmount()*100,
			'discountAmount'=>$order->getDiscountAmount(),
			'shippingAmount'=>$order->getShippingAmount(),
			'orderDate'=>$order->getCreatedAtFormated(long),
			'detailTax'=>array('taxAmount'=>$order->getTaxAmount()*100),
			'lineItemData' => $this->getLineItemData($payment)
		);
		return $hash;
	}

	public function getFraudCheck(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		$hash = array('customerIpAddress'=> $order->getRemoteIp()
		);
		return $hash;
	}

	public function processResponse(Varien_Object $payment,$litleResponse){
		$message = XmlParser::getAttribute($litleResponse,'litleOnlineResponse','message');
		if ($message == "Valid Format"){
			$isSale = ($payment->getCcTransId() != NULL)? FALSE : TRUE;
			if( isset($litleResponse))
			{
				$litleResponseCode = XMLParser::getNode($litleResponse,'response');
				if($litleResponseCode != "000")
				{
					$payment
					->setStatus("Rejected")
					->setCcTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setLastTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setTransactionId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setIsTransactionClosed(0)
					->setTransactionAdditionalInfo("additional_information", XMLParser::getNode($litleResponse,'message'));
						
					if($isSale)
					throw new Mage_Payment_Model_Info_Exception(Mage::helper('core')->__("Transaction was not approved. Contact us or try again later."));
					else
					throw new Mage_Payment_Model_Info_Exception(Mage::helper('core')->__("Transaction was not approved. Contact Litle or try again later."));
				}
				else
				{
					$payment
					->setStatus("Approved")
					->setCcTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setLastTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setTransactionId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setIsTransactionClosed(0)
					->setTransactionAdditionalInfo("additional_information", XMLParser::getNode($litleResponse,'message'));
				}
				return $this;
			}
		}
		else{
			Mage::throwException($message);
		}
	}
	/**
	 * this method is called if we are just authorising
	 * a transaction
	 */
	public function authorize(Varien_Object $payment, $amount)
	{
		if (preg_match("/sales_order_create/i", $_SERVER['REQUEST_URI']) && ($this->getConfigData('paypage_enable') == "1") )
		{
			$payment
			->setStatus("N/A")
			->setCcTransId("Litle VT")
			->setLastTransId("Litle VT")
			->setTransactionId("Litle VT")
			->setIsTransactionClosed(0)
			->setCcType("Litle VT");
		}
		else{
			$order = $payment->getOrder();
			$orderId =  $order->getIncrementId();
			$amountToPass = ($amount* 100);
			if (!empty($order)){
				$hash = array(
				 					'orderId'=> $orderId,
				 					'amount'=> $amountToPass,
				 					'orderSource'=> "ecommerce",
									'billToAddress'=> $this->getBillToAddress($payment),
									'shipToAddress'=> $this->getAddressInfo($payment),
									'cardholderAuthentication'=> $this->getFraudCheck($payment),
									'enhancedData'=>$this->getEnhancedData($payment),
									'customBilling'=>$this->getCustomBilling()
				);
				$payment_hash = $this->creditCardOrPaypage($payment);
				$hash_temp = array_merge($hash,$payment_hash);
				$merchantData = $this->merchantData($payment);
				$hash_in = array_merge($hash_temp,$merchantData);
				$litleRequest = new LitleOnlineRequest();
				$litleResponse = $litleRequest->authorizationRequest($hash_in);
				$this->processResponse($payment,$litleResponse);
					
				Mage::log("About to call helper");
				Mage::helper("palorus")->saveCustomerInsight($payment, $litleResponse);
				Mage::helper("palorus")->saveVault($payment, $litleResponse);
				Mage::log("Back from helper");
			}
		}
	}

	/**
	 * this method is called if we are authorising AND
	 * capturing a transaction
	 */
	public function capture (Varien_Object $payment, $amount)
	{
		if (preg_match("/sales_order_create/i", $_SERVER['REQUEST_URI']) && ($this->getConfigData('paypage_enable') == "1") )
		{
			$payment
			->setStatus("N/A")
			->setCcTransId("Litle VT")
			->setLastTransId("Litle VT")
			->setTransactionId("Litle VT")
			->setIsTransactionClosed(0)
			->setCcType("Litle VT");
				
			return;
		}

		$this->isFromVT($payment, "capture");

		$order = $payment->getOrder();
		if (!empty($order)){
				
			$orderId =$order->getIncrementId();
			$amountToPass = ($amount* 100);
			$isPartialCapture = ($amount < $order->getGrandTotal()) ? "true" : "false";
			$isSale = ($payment->getCcTransId() != NULL)? FALSE : TRUE;
				
			if( !$isSale )
			{
				$hash = array(
								'litleTxnId' => $payment->getParentTransactionId(),
								'amount' => $amountToPass,
								'partial' => $isPartialCapture
				);
			} else {
				$hash_temp = array(
			 					'orderId'=> $orderId,
			 					'amount'=> $amountToPass,
			 					'orderSource'=> "ecommerce",
								'billToAddress'=> $this->getBillToAddress($payment),
								'shipToAddress'=> $this->getAddressInfo($payment),
				);
				$payment_hash = $this->creditCardOrPaypage($payment);
				$hash = array_merge($hash_temp,$payment_hash);
			}
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();

			if( $isSale )
			{
				$litleResponse = $litleRequest->saleRequest($hash_in);
			} else {
				$litleResponse = $litleRequest->captureRequest($hash_in);
			}
		}
		$this->processResponse($payment,$litleResponse);
	}

	/**
	 * called if refunding
	 */
	public function refund (Varien_Object $payment, $amount)
	{
		$this->isFromVT($payment, "refund");

		$order = $payment->getOrder();
		$amountToPass = ($amount* 100);
		if (!empty($order)){
			$hash = array(
						'litleTxnId' => $payment->getCcTransId(),
						'amount' => $amountToPass
			);
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();
			$litleResponse = $litleRequest->creditRequest($hash_in);
		}
		$this->processResponse($payment,$litleResponse);
		return $this;
	}

	/**
	 * called if voiding a payment
	 */
	public function void (Varien_Object $payment)
	{
		$this->isFromVT($payment, "void");

		$order = $payment->getOrder();
		if (!empty($order)){
			$hash = array(
						'litleTxnId' => $payment->getCcTransId()
			);
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();
			$litleResponse = $litleRequest->authReversalRequest($hash_in);
		}
		$this->processResponse($payment,$litleResponse);
	}




}
