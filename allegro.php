<?php
/**
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}


class Allegro extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'allegro';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Damian Kitlas';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Allegro Integration');
        $this->description = $this->l('Allegro REST API Integration');

        $this->confirmUninstall = $this->l('All integration settings will be deleted');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('ALLEGRO_TEST_MODE', false);

        return $this->installSql() &&
            $this->installTabs() &&
            parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('ALLEGRO_TEST_MODE');

        return parent::uninstall() && $this->uninstallTabs() && $this->uninstallSql();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitAllegroModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAllegroModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Wersja testowa'),
                        'name' => 'ALLEGRO_TEST_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Korzystaj z Allegro sandbox'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Włącz')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Wyłącz')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'ALLEGRO_CLIENT_ID',
                        'label' => $this->l('Client ID'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'ALLEGRO_CLIENT_SECRET',
                        'label' => $this->l('Client secret'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'ALLEGRO_TEST_MODE' => Configuration::get('ALLEGRO_TEST_MODE', true),
            'ALLEGRO_CLIENT_ID' => Configuration::get('ALLEGRO_CLIENT_ID', null),
            'ALLEGRO_CLIENT_SECRET' => Configuration::get('ALLEGRO_CLIENT_SECRET', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Install the tab
     */
    public function installTabs(){
        try{

            // Dodaj zakładkę "Allegro" do menu admina
            $parentTab = new Tab();
            $parentTab->name = array();
            foreach (Language::getLanguages() as $lang) {
                $parentTab->name[$lang['id_lang']] = 'Allegro';
            }
            $parentTab->class_name = 'AdminAllegro';
            $parentTab->icon = 'whatshot';
            $parentTab->id_parent = (int) Tab::getIdFromClassName('SELL');
            $parentTab->module = $this->name;
            if (!$parentTab->add()) {
                return false;
            }

            $tab = new Tab();
            $tab->name = array();
            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = 'Konta';
            }
            $tab->class_name = 'AdminAllegroAccounts';
            $tab->id_parent = $parentTab->id;
            $tab->module = $this->name;
            if (!$tab->add()) {
                return false;
            }

        }catch (Exception $e){
            echo $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Uninstall the tab
     */
    public function uninstallTabs(){
        $tabId = (int) Tab::getIdFromClassName('AdminAllegro');
        if($tabId){
            $tab = new Tab($tabId);
            try{
                $tab->delete();
            }catch (Exception $e){
                echo $e->getMessage();
                return false;
            }
        }
        return true;
    }

    public function installSql(): bool {
        // Tworzenie tabeli
//        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'allegro_accounts` (
//        `id_allegro_accounts` INT AUTO_INCREMENT PRIMARY KEY,
//        `name` VARCHAR(255) NOT NULL,
//        `authorized` TINYINT(1) NOT NULL DEFAULT 0
//        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $sql = "CREATE TABLE IF NOT EXISTS ps_allegro_account (id_allegro_account INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, authorized TINYINT(1) DEFAULT '0' NOT NULL, PRIMARY KEY(id_allegro_account))";

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }
        return true;
    }

    public function uninstallSql(): bool {
        // usuwanie tabeli
        $sql = 'DROP TABLE IF EXISTS `ps_allegro_account`;';

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }
        return true;
    }
}
