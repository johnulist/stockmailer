<?php

/**
 * @author Alexdu98
 * @copyright 2017 Alexdu98
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
	exit;
}

class StockMailer extends Module
{
	public function __construct()
	{
		$this->name = 'stockmailer';
		$this->tab = 'administration';
		$this->version = '1.0.0';
		$this->author = 'Alexdu98';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Stock Mailer');
		$this->description = $this->l('Notify when the stock is updated.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall ?');

		if (!Configuration::get('STOCKMAILER_NAME'))
			$this->warning = $this->l('No name provided');
	}

	public function install()
	{
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);

		if (!parent::install() ||
			!Configuration::updateValue('STOCKMAILER_NAME', 'Stock Mailer') ||
			!Configuration::updateValue('STOCKMAILER_RECIPIENT_EMAIL', Configuration::get('PS_SHOP_EMAIL')) ||
			!$this->registerHook('actionUpdateQuantity')
			)
			return false;

		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall() ||
			!Configuration::deleteByName('STOCKMAILER_NAME') ||
			!Configuration::deleteByName('STOCKMAILER_RECIPIENT_EMAIL')
			)
			return false;

		return true;
	}

	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit'.$this->name))
		{
			$recipientEmail = strval(Tools::getValue('RECIPIENT_EMAIL'));
			if (!$recipientEmail
				|| empty($recipientEmail)
				|| !Validate::isEmail($recipientEmail))
				$output .= $this->displayError($this->l('Invalid recipient email value'));
			else
			{
				Configuration::updateValue('STOCKMAILER_RECIPIENT_EMAIL', $recipientEmail);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->displayForm();
	}

	public function displayForm()
	{
	    // Get default language
	    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

	    // Init Fields form array
	    $fields_form[0]['form'] = array(
	        'legend' => array(
	            'title' => $this->l('Settings'),
	        ),
	        'input' => array(
	            array(
	                'type' => 'text',
	                'label' => $this->l('Recipient email'),
	                'name' => 'RECIPIENT_EMAIL',
	                'size' => 20,
	                'placeholder' => 'Recipient email',
	                'required' => true
	            )
	        ),
	        'submit' => array(
	            'title' => $this->l('Save'),
	            'class' => 'btn btn-default pull-right'
	        )
	    );

	    $helper = new HelperForm();

	    // Module, token and currentIndex
	    $helper->module = $this;
	    $helper->name_controller = $this->name;
	    $helper->token = Tools::getAdminTokenLite('AdminModules');
	    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

	    // Language
	    $helper->default_form_language = $default_lang;
	    $helper->allow_employee_form_lang = $default_lang;

	    // Title and toolbar
	    $helper->title = $this->displayName;
	    $helper->show_toolbar = true;        // false -> remove toolbar
	    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
	    $helper->submit_action = 'submit'.$this->name;
	    $helper->toolbar_btn = array(
	        'save' =>
	        array(
	            'desc' => $this->l('Save'),
	            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
	            '&token='.Tools::getAdminTokenLite('AdminModules'),
	        ),
	        'back' => array(
	            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
	            'desc' => $this->l('Back to list')
	        )
	    );

	    // Load current value
	    $helper->fields_value['RECIPIENT_EMAIL'] = Configuration::get('STOCKMAILER_RECIPIENT_EMAIL') ? 
	    Configuration::get('STOCKMAILER_RECIPIENT_EMAIL') : Configuration::get('PS_SHOP_EMAIL');

	    return $helper->generateForm($fields_form);
	}

	public function hookActionUpdateQuantity($params)
    {
    	$id_product = (int)$params['id_product'];
		$id_product_attribute = (int)$params['id_product_attribute'];
		$quantity = (int)$params['quantity'];

		$context = Context::getContext();
		$id_shop = (int)$context->shop->id;
		$id_lang = (int)$context->language->id;
		$product = new Product($id_product, false, $id_lang, $id_shop, $context);
		$product_has_attributes = $product->hasAttributes();
		$configuration = Configuration::getMultiple(
			array(
				'MA_LAST_QTIES',
				'PS_STOCK_MANAGEMENT',
				'PS_SHOP_EMAIL',
				'PS_SHOP_NAME'
			), null, null, $id_shop
		);
		$ma_last_qties = (int)$configuration['MA_LAST_QTIES'];

		$check_oos = ($product_has_attributes && $id_product_attribute) || (!$product_has_attributes && !$id_product_attribute);

		if ($check_oos && $product->active == 1 && $configuration['PS_STOCK_MANAGEMENT'])
		{

			$iso = Language::getIsoById($id_lang);
			$product_name = Product::getProductName($id_product, $id_product_attribute, $id_lang);
			$template_vars = array(
				'{qty}' => $quantity,
				'{product}' => $product_name
			);

			if (file_exists(dirname(__FILE__).'/mails/'.$iso.'/updatestock.txt') &&
				file_exists(dirname(__FILE__).'/mails/'.$iso.'/updatestock.html')
			)
			{
		    	Mail::Send(
					$id_lang,
					'updatestock',
					Mail::l('Update stock', $id_lang),
					$template_vars,
					Configuration::get('STOCKMAILER_RECIPIENT_EMAIL'),
					null,
					strval($configuration['PS_SHOP_EMAIL']),
					strval($configuration['PS_SHOP_NAME']),
					null,
					null,
					dirname(__FILE__).'/mails/',
					false,
					$id_shop
				);
		    }
		}
    }
}