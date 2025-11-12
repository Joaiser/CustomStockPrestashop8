<?php
if (!defined('_PS_VERSION_')) {
  exit;
}

require_once __DIR__ . '/classes/models/CustomStockModel.php';
require_once __DIR__ . '/classes/repositories/CustomStockRepository.php';
require_once __DIR__ . '/classes/services/CustomStockService.php';
require_once __DIR__ . '/classes/services/CustomStockLogger.php';
require_once __DIR__ . '/classes/services/CustomStockSearchService.php';
require_once __DIR__ . '/classes/handlers/CustomStockAjaxHandler.php';
require_once __DIR__ . '/classes/handlers/CustomStockHookHandler.php';

class CustomStockDisplay extends Module
{
  private $service;
  private $logger;
  private $searchService;
  private $ajaxHandler;
  private $hookHandler;

  public function __construct()
  {
    $this->name = 'customstockdisplay';
    $this->tab = 'administration';
    $this->version = '1.0.1';
    $this->author = 'Aitor';
    $this->need_instance = 0;
    $this->bootstrap = true;
    $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

    parent::__construct();

    $this->displayName = $this->l('Stock Personalizado');
    $this->description = $this->l('Muestra stock personalizado según configuración.');

    $this->service = new CustomStockService($this);
    $this->logger = new CustomStockLogger($this);
    $this->searchService = new CustomStockSearchService($this);
    $this->ajaxHandler = new CustomStockAjaxHandler($this);
    $this->hookHandler = new CustomStockHookHandler($this);
  }

  public function upgrade($version)
  {
    $this->logger->info("Iniciando upgrade a versión: " . $version);

    if (version_compare($version, '1.0.1', '<')) {
      $this->logger->info("Actualizando a versión 1.0.1");
      // Cambios en BD si son necesarios
    }

    return parent::upgrade($version);
  }

  public function install()
  {
    if (!parent::install() || !$this->installDb()) {
      return false;
    }

    // Crear menú admin (continuar aunque falle)
    $this->installTab();

    // Registrar hooks
    $hooks = [
      'displayHeader',
      'actionProductUpdate',
      'displayBackOfficeHeader',
      'displayProductAdditionalInfo',
      'displayFooterProduct'
    ];

    foreach ($hooks as $hook) {
      if (!$this->registerHook($hook)) {
        $this->_errors[] = $this->l('Error registrando hook: ') . $hook;
      }
    }

    $this->logger->info('Módulo instalado correctamente');
    return true;
  }

  public function uninstall()
  {
    // Eliminar tab
    $id_tab = (int)Tab::getIdFromClassName('AdminCustomStockDisplay');
    if ($id_tab) {
      $tab = new Tab($id_tab);
      $tab->delete();
    }

    if (!$this->uninstallDb() || !parent::uninstall()) {
      return false;
    }

    $this->logger->info('Módulo desinstalado correctamente');
    return true;
  }

  /**
   * HOOKS FRONTEND - Delegados al handler
   */
  public function hookDisplayHeader()
  {
    return $this->hookHandler->handleDisplayHeader();
  }

  public function hookDisplayProductAdditionalInfo($params)
  {
    return $this->hookHandler->handleDisplayProductAdditionalInfo($params);
  }

  public function hookDisplayFooterProduct($params)
  {
    return $this->hookHandler->handleDisplayFooterProduct($params);
  }

  /**
   * HOOKS ADMIN - Delegados al handler
   */
  public function hookDisplayBackOfficeHeader()
  {
    return $this->hookHandler->handleDisplayBackOfficeHeader();
  }

  public function getContent()
  {
    $this->ensureAdminTabExists();

    // Manejar peticiones AJAX - Delegadas al handler
    if (Tools::isSubmit('ajax') && Tools::getValue('action') == 'searchProducts') {
      header('Content-Type: application/json');
      $this->ajaxHandler->handleSearchProducts();
      exit;
    }

    if (Tools::isSubmit('action') && Tools::getValue('action') == 'saveConfiguration') {
      header('Content-Type: application/json');
      $this->ajaxHandler->handleSaveConfiguration();
      exit;
    }

    return $this->renderAdminInterface();
  }

  /**
   * MÉTODOS PRIVADOS - INSTALACIÓN
   */
  private function installDb()
  {
    $this->logger->info('Instalando base de datos');

    $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "customstockdisplay` (
                `id_customstock` INT(11) NOT NULL AUTO_INCREMENT,
                `id_product` INT(11) NOT NULL,
                `id_product_attribute` INT(11) NOT NULL DEFAULT '0',
                `id_shop` INT(11) NOT NULL DEFAULT '1',
                `stock_min` INT(11) NOT NULL DEFAULT '0',
                `stock_display` INT(11) NOT NULL DEFAULT '0',
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_customstock`),
                UNIQUE KEY `id_product_attribute_shop` (`id_product`, `id_product_attribute`, `id_shop`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

    $result = Db::getInstance()->execute($sql);

    if ($result) {
      $this->logger->info('Base de datos instalada correctamente');
    } else {
      $this->logger->error('ERROR instalando base de datos: ' . Db::getInstance()->getMsgError());
    }

    return $result;
  }

  private function uninstallDb()
  {
    $this->logger->info('Desinstalando base de datos');
    $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "customstockdisplay`;";
    $result = Db::getInstance()->execute($sql);

    if ($result) {
      $this->logger->info('Base de datos desinstalada correctamente');
    } else {
      $this->logger->error('ERROR desinstalando base de datos: ' . Db::getInstance()->getMsgError());
    }

    return $result;
  }

  private function installTab()
  {
    $tab = new Tab();
    $tab->class_name = 'AdminCustomStockDisplay';
    $tab->module = $this->name;
    $tab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');

    $languages = Language::getLanguages();
    foreach ($languages as $lang) {
      $tab->name[$lang['id_lang']] = 'Stock Personalizado';
    }

    if ($tab->add()) {
      $this->logger->info('✅ Menú admin creado - ID: ' . $tab->id);
      return true;
    } else {
      $error = Db::getInstance()->getMsgError();
      $this->logger->error('ERROR creando tab: ' . $error);
      return false;
    }
  }

  /**
   * MÉTODOS PRIVADOS - ADMIN INTERFACE
   */
  private function ensureAdminTabExists()
  {
    $tabId = Tab::getIdFromClassName('AdminCustomStockDisplay');

    if (!$tabId) {
      $this->logger->warning("🚨 TAB NO EXISTE - CREANDO...");
      $result = $this->installTab();

      if ($result) {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&conf=4');
      }
    }
  }

  private function renderAdminInterface()
  {
    $output = '';

    // Manejar acciones de logs
    if (Tools::getValue('refresh_logs')) {
      $output .= $this->displayConfirmation($this->l('Logs actualizados'));
    }

    if (Tools::getValue('clear_logs')) {
      if ($this->logger->clear()) {
        $output .= $this->displayConfirmation($this->l('Logs limpiados correctamente'));
      }
    }

    $output .= $this->renderForm();
    $output .= $this->displayLogsSection();

    return $output;
  }

  private function renderForm()
  {
    $page = (int)Tools::getValue('page', 1);
    $limit = 50;
    $id_lang = (int)$this->context->language->id;
    $id_shop = (int)$this->context->shop->id;

    // Obtener datos para el template
    $registered_count = $this->service->getRegisteredCount();
    $productsData = $this->searchService->getProductsForAdminPage($page, $limit, $id_lang, $id_shop);
    $categories = Category::getCategories($this->context->language->id, false);

    $this->context->smarty->assign([
      'products' => $productsData['products'],
      'categories' => $categories,
      'current_page' => $page,
      'total_pages' => $productsData['total_pages'],
      'token' => Tools::getAdminTokenLite('AdminModules'),
      'module_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
      'registered_count' => $registered_count,
      'admin_custom_stock_url' => $this->context->link->getAdminLink('AdminCustomStockDisplay'),
    ]);

    return $this->display(__FILE__, 'views/templates/admin/config.tpl');
  }

  private function displayLogsSection()
  {
    $logContent = $this->logger->getContent();

    $this->context->smarty->assign([
      'log_content' => $logContent,
      'log_file_path' => $this->logger->getFilePath(),
      'log_stats' => $this->logger->getStats()
    ]);

    return $this->display(__FILE__, 'views/templates/admin/logs.tpl');
  }
}
