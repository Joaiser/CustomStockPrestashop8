<?php
if (!defined('_PS_VERSION_')) {
  exit;
}

require_once __DIR__ . '/classes/CustomStockModel.php';
require_once __DIR__ . '/classes/CustomStockRepository.php';
require_once __DIR__ . '/classes/CustomStockService.php';
require_once __DIR__ . '/classes/CustomStockLogger.php';
require_once __DIR__ . '/services/CustomStockSearchService.php';

class CustomStockDisplay extends Module
{
  private $service;
  private $logger;
  private $searchService;

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
   * HOOKS FRONTEND
   */
  public function hookDisplayHeader()
  {
    if ($this->context->controller->php_self == 'product') {
      $id_product = (int)Tools::getValue('id_product');

      if ($id_product && $this->service->shouldLoadFrontendAssets($id_product)) {
        $this->context->controller->registerJavascript(
          'module-customstockdisplay-front',
          'modules/' . $this->name . '/views/js/stock-display-front.js',
          ['position' => 'bottom', 'priority' => 150]
        );

        $this->context->controller->registerStylesheet(
          'customstockdisplay-css',
          'modules/' . $this->name . '/views/css/customstockdisplay.css',
          ['position' => 'head', 'priority' => 150]
        );

        $this->logger->info("✅ CSS/JS registrados para producto $id_product");
      }
    }
    return '';
  }

  public function hookDisplayProductAdditionalInfo($params)
  {
    $id_product = (int)Tools::getValue('id_product');
    if (!$id_product) {
      return '';
    }

    $stockConfig = $this->service->getStockConfigForProduct($id_product);

    if (empty($stockConfig)) {
      return '';
    }

    $stockConfigJson = Tools::jsonEncode($stockConfig);
    if ($stockConfigJson === false) {
      return '';
    }

    return "
            <script>
                window.stockDisplayConfig = $stockConfigJson;
            </script>
        ";
  }

  public function hookDisplayFooterProduct($params)
  {
    $id_product = (int)Tools::getValue('id_product');
    if (!$id_product) {
      return '';
    }

    if ($this->service->shouldLoadFrontendAssets($id_product)) {
      return '
                <script>
                    if (typeof window.stockDisplayConfig !== "undefined") {
                        // Inicialización adicional si es necesaria
                    }
                </script>';
    }

    return '';
  }

  /**
   * HOOKS ADMIN
   */
  public function hookDisplayBackOfficeHeader()
  {
    $controller = Tools::getValue('controller');
    $configure = Tools::getValue('configure');

    if ($controller === 'AdminModules' && $configure === $this->name) {
      $this->context->controller->addJS($this->_path . 'views/js/admin.js');
    }

    return '';
  }

  public function getContent()
  {
    $this->ensureAdminTabExists();

    // Manejar peticiones AJAX
    if (Tools::isSubmit('ajax') && Tools::getValue('action') == 'searchProducts') {
      header('Content-Type: application/json');
      $this->ajaxProcessSearchProducts();
      exit;
    }

    if (Tools::isSubmit('action') && Tools::getValue('action') == 'saveConfiguration') {
      header('Content-Type: application/json');
      $this->ajaxProcessSaveConfiguration();
      exit;
    }

    return $this->renderAdminInterface();
  }

  /**
   * MÉTODOS AJAX
   */
  public function ajaxProcessSearchProducts()
  {
    try {
      header('Content-Type: application/json');

      $search = Tools::getValue('search', '');
      $page = max(1, (int)Tools::getValue('page', 1));
      $limit = min(max(1, (int)Tools::getValue('limit', 50)), 100);
      $id_lang = (int)$this->context->language->id;
      $id_shop = (int)$this->context->shop->id;

      $productsData = $this->searchService->searchProducts($search, $page, $limit, $id_lang, $id_shop);

      echo json_encode([
        'success' => true,
        'products' => $productsData['products'],
        'pagination' => $productsData['pagination']
      ]);
    } catch (Exception $e) {
      $this->logger->error('ERROR en ajaxProcessSearchProducts: ' . $e->getMessage());
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
      ]);
    }
    exit;
  }

  public function ajaxProcessSaveConfiguration()
  {
    try {
      header('Content-Type: application/json');

      $productsData = Tools::getValue('products', []);
      $repository = new CustomStockRepository($this);
      $savedCount = 0;

      foreach ($productsData as $productJson) {
        $productData = json_decode($productJson, true);

        if (!is_array($productData)) {
          continue;
        }

        $id_product = (int)($productData['id_product'] ?? 0);
        $id_product_attribute = (int)($productData['id_product_attribute'] ?? 0);
        $stock_min = max(0, (int)($productData['stock_min'] ?? 0));
        $stock_display = max(0, (int)($productData['stock_display'] ?? 0));

        if ($id_product > 0) {
          $result = $repository->saveConfiguration(
            $id_product,
            $id_product_attribute,
            $stock_min,
            $stock_display
          );

          if ($result) {
            $savedCount++;
          }
        }
      }

      Tools::generateIndex();

      echo json_encode([
        'success' => true,
        'message' => "Configuración guardada correctamente para $savedCount elementos",
        'saved_count' => $savedCount
      ]);
    } catch (Exception $e) {
      $this->logger->error('ERROR en ajaxProcessSaveConfiguration: ' . $e->getMessage());
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
      ]);
    }
    exit;
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
