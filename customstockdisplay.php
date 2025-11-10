<?php
if (!defined('_PS_VERSION_')) {
  exit;
}

class CustomStockDisplay extends Module
{
  private $logFile;

  public function __construct()
  {
    $this->name = 'customstockdisplay';
    $this->tab = 'administration';
    $this->version = '1.0.1';
    $this->author = 'Aitor';
    $this->need_instance = 0;
    $this->bootstrap = true;
    $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => _PS_VERSION_);

    parent::__construct();

    $this->displayName = $this->l('Stock Personalizado');
    $this->description = $this->l('Muestra stock personalizado según configuración.');

    $this->logFile = dirname(__FILE__) . '/logs/customstockdisplay.log';
  }

  public function upgrade($version)
  {
    $this->writeLog("Iniciando upgrade a versión: " . $version);

    // Para versiones específicas puedes hacer cambios en BD si es necesario
    if (version_compare($version, '1.0.1', '<')) {
      $this->writeLog("Actualizando a versión 1.0.1");
      // Aquí puedes añadir cambios en BD si los necesitas
    }

    return parent::upgrade($version);
  }

  public function install()
  {
    if (!parent::install()) {
      return false;
    }

    if (!$this->installDb()) {
      $this->_errors[] = $this->l('Error instalando base de datos');
      return false;
    }

    // CREAR EL MENÚ PARA EL CONTROLADOR
    if (!$this->installTab()) {
      $this->_errors[] = $this->l('Error creando menú admin');
      // Continuamos aunque falle el menú
    }

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

    $this->writeLog('Módulo instalado correctamente');
    return true;
  }

  private function installTab()
  {
    $tab = new Tab();
    $tab->class_name = 'AdminCustomStockDisplay';
    $tab->module = $this->name;
    $tab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');

    // AÑADIR ESTO PARA DEBUG
    $languages = Language::getLanguages();
    foreach ($languages as $lang) {
      $tab->name[$lang['id_lang']] = 'Stock Personalizado';
    }

    if (!$tab->add()) {
      $error = Db::getInstance()->getMsgError();
      $this->writeLog('ERROR creando tab: ' . $error, 'ERROR');
      return false;
    }

    $this->writeLog('✅ Menú admin creado - ID: ' . $tab->id);
    return true;
  }

  // También añade el uninstall para el tab
  public function uninstall()
  {
    // Eliminar el tab
    $id_tab = (int)Tab::getIdFromClassName('AdminCustomStockDisplay');
    if ($id_tab) {
      $tab = new Tab($id_tab);
      if (!$tab->delete()) {
        $this->_errors[] = $this->l('Error eliminando menú admin');
      }
    }

    if (!$this->uninstallDb()) {
      $this->_errors[] = $this->l('Error desinstalando base de datos');
      return false;
    }

    if (!parent::uninstall()) {
      return false;
    }

    $this->writeLog('Módulo desinstalado correctamente');
    return true;
  }


  private function writeLog($message, $level = 'INFO')
  {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";

    try {
      file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
      // Silenciar errores de log para no romper la instalación
    }
  }

  private function installDb()
  {
    $this->writeLog('Instalando base de datos');

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
      $this->writeLog('Base de datos instalada correctamente');
    } else {
      $this->writeLog('ERROR instalando base de datos: ' . Db::getInstance()->getMsgError(), 'ERROR');
    }

    return $result;
  }

  private function uninstallDb()
  {
    $this->writeLog('Desinstalando base de datos');
    $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "customstockdisplay`;";
    $result = Db::getInstance()->execute($sql);

    if ($result) {
      $this->writeLog('Base de datos desinstalada correctamente');
    } else {
      $this->writeLog('ERROR desinstalando base de datos: ' . Db::getInstance()->getMsgError(), 'ERROR');
    }

    return $result;
  }

  public function hookDisplayBackOfficeHeader()
  {
    // Verificar que estamos en la página de configuración del módulo
    $controller = Tools::getValue('controller');
    $configure = Tools::getValue('configure');

    if ($controller === 'AdminModules' && $configure === $this->name) {
      $this->context->controller->addJS($this->_path . 'views/js/admin.js');

      // // DEBUG: Verificar que la ruta es correcta
      // $jsPath = $this->_path . 'views/js/admin.js';
      // $this->writeLog("Intentando cargar JS: " . $jsPath);

      // // Verificar si el archivo existe físicamente
      // $physicalPath = dirname(__FILE__) . '/views/js/admin.js';
      // if (file_exists($physicalPath)) {
      //   $this->writeLog("✅ Archivo JS existe: " . $physicalPath);
      // } else {
      //   $this->writeLog("❌ Archivo JS NO existe: " . $physicalPath, 'ERROR');
      // }
    }

    return '';
  }

  public function getContent()
  {

    $tabId = Tab::getIdFromClassName('AdminCustomStockDisplay');
    $this->writeLog("🔍 DEBUG Tab Status: " . ($tabId ? "EXISTE (ID: $tabId)" : "NO EXISTE"));

    // 🔥 TEMPORAL: Forzar creación si no existe
    if (!$tabId) {
      $this->writeLog("🚨 TAB NO EXISTE - CREANDO...");
      $result = $this->installTab();
      $this->writeLog("🚨 RESULTADO CREACIÓN TAB: " . ($result ? "ÉXITO" : "FALLO"));

      if ($result) {
        // Redirigir para refrescar
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&conf=4');
      }
    }

    if (Tools::getValue('force_install_tab')) {
      if ($this->forceInstallTab()) {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name . '&conf=4');
      }
    }

    // DEBUG: Verificar estado del tab
    $tabId = Tab::getIdFromClassName('AdminCustomStockDisplay');
    $this->writeLog("🔍 DEBUG Tab ID: " . ($tabId ? $tabId : 'NO EXISTE'));



    // Manejar peticiones AJAX primero
    if (Tools::isSubmit('ajax') && Tools::getValue('action') == 'searchProducts') {
      header('Content-Type: application/json');
      $this->ajaxProcessSearchProducts();
      exit;
    }

    // NUEVO: Manejar guardado de configuración via AJAX
    if (Tools::isSubmit('action') && Tools::getValue('action') == 'saveConfiguration') {
      header('Content-Type: application/json');
      $this->ajaxProcessSaveConfiguration();
      exit;
    }

    $output = '';

    // Manejar acciones de logs
    if (Tools::getValue('refresh_logs')) {
      $output .= $this->displayConfirmation($this->l('Logs actualizados'));
    }

    if (Tools::getValue('clear_logs')) {
      if (file_exists($this->logFile)) {
        file_put_contents($this->logFile, '');
        $this->writeLog('Logs limpiados manualmente desde el admin');
        $output .= $this->displayConfirmation($this->l('Logs limpiados correctamente'));
      }
    }

    $output .= $this->renderForm();
    $output .= $this->displayLogsSection();

    return $output;
  }

  /**
   * Método temporal para forzar la instalación del tab
   * Se ejecuta desde getContent() con un parámetro
   */
  private function forceInstallTab()
  {
    // Verificar si ya existe
    $id_tab = Tab::getIdFromClassName('AdminCustomStockDisplay');

    if ($id_tab) {
      $this->writeLog("✅ Tab ya existe con ID: " . $id_tab);
      return true;
    }

    // Crear nuevo tab
    $tab = new Tab();
    $tab->class_name = 'AdminCustomStockDisplay';
    $tab->module = $this->name;
    $tab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');

    $languages = Language::getLanguages();
    foreach ($languages as $lang) {
      $tab->name[$lang['id_lang']] = 'Stock Personalizado';
    }

    if ($tab->add()) {
      $this->writeLog('✅ FORZADO: Menú admin creado - ID: ' . $tab->id);
      return true;
    } else {
      $error = Db::getInstance()->getMsgError();
      $this->writeLog('❌ ERROR forzando tab: ' . $error, 'ERROR');
      return false;
    }
  }

  protected function displayLogsSection()
  {
    $logContent = 'No hay logs disponibles';

    if (file_exists($this->logFile)) {
      $logContent = file_get_contents($this->logFile);
      if (empty($logContent)) {
        $logContent = 'El archivo de logs está vacío';
      }
    }

    $this->context->smarty->assign([
      'log_content' => $logContent,
      'log_file_path' => $this->logFile,
    ]);

    return $this->display(__FILE__, 'views/templates/admin/logs.tpl');
  }

  // Eliminamos processConfiguration ya que ahora usamos ajaxProcessSaveConfiguration

  protected function renderForm()
  {
    $this->writeLog('Renderizando formulario admin');

    $page = (int)Tools::getValue('page', 1);
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $id_lang = (int)$this->context->language->id;
    $id_shop = (int)$this->context->shop->id;

    // OBTENER CONTADOR DE PRODUCTOS REGISTRADOS
    $registered_count = (int)Db::getInstance()->getValue(
      'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
         WHERE id_shop = ' . $id_shop
    );

    // OBTENER CATEGORÍAS PARA EL FILTRO
    $categories = Category::getCategories($id_lang, false);

    // OBTENER PRODUCTOS CON MÁS INFORMACIÓN
    $sql = 'SELECT 
                p.id_product, 
                pl.name, 
                p.reference,
                GROUP_CONCAT(DISTINCT cp.id_category) as categories,
                (SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_attribute WHERE id_product = p.id_product) as combinations_count
            FROM `' . _DB_PREFIX_ . 'product` p
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                p.id_product = pl.id_product 
                AND pl.id_lang = ' . $id_lang . '
                AND pl.id_shop = ' . $id_shop . '
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (
                p.id_product = cp.id_product
            )
            WHERE p.active = 1
            GROUP BY p.id_product
            ORDER BY pl.name ASC
            LIMIT ' . (int)$offset . ', ' . (int)$limit;

    $products = Db::getInstance()->executeS($sql);

    $configurations = [];
    if (!empty($products)) {
      $productIds = array_map(function ($product) {
        return (int)$product['id_product'];
      }, $products);

      $configs = Db::getInstance()->executeS(
        'SELECT id_product, stock_min, stock_display 
            FROM ' . _DB_PREFIX_ . 'customstockdisplay 
            WHERE id_product IN (' . implode(',', $productIds) . ')
            AND id_shop = ' . $id_shop
      );

      foreach ($configs as $config) {
        $configurations[$config['id_product']] = $config;
      }
    }

    $productsData = [];
    foreach ($products as $product) {
      $id_product = (int)$product['id_product'];
      $productsData[] = [
        'id_product' => $id_product,
        'name' => $product['name'] ?: 'Product #' . $id_product,
        'reference' => $product['reference'] ?: 'N/A',
        'categories' => $product['categories'] ? explode(',', $product['categories']) : [],
        'has_combinations' => ($product['combinations_count'] > 0),
        'stock_min' => $configurations[$id_product]['stock_min'] ?? 0,
        'stock_display' => $configurations[$id_product]['stock_display'] ?? 0,
      ];
    }

    $totalProducts = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` WHERE active = 1');
    $totalPages = ceil($totalProducts / $limit);

    $this->context->smarty->assign([
      'products' => $productsData,
      'categories' => $categories,
      'current_page' => $page,
      'total_pages' => $totalPages,
      'token' => Tools::getAdminTokenLite('AdminModules'),
      'module_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
      'registered_count' => $registered_count,
      'admin_custom_stock_url' => $this->context->link->getAdminLink('AdminCustomStockDisplay'), // ← AÑADE ESTA LÍNEA
    ]);

    return $this->display(__FILE__, 'views/templates/admin/config.tpl');
  }

  public function ajaxProcessSearchProducts()
  {
    try {
      header('Content-Type: application/json');

      $search = Tools::getValue('search', '');
      $page = (int)Tools::getValue('page', 1);
      $limit = (int)Tools::getValue('limit', 50);
      $offset = ($page - 1) * $limit;
      $id_lang = (int)$this->context->language->id;
      $id_shop = (int)$this->context->shop->id;

      // DEBUG: Ver qué estamos buscando
      $this->writeLog("=== DEBUG AJAX SEARCH ===");
      $this->writeLog("Search: '$search', Page: $page, Limit: $limit, Offset: $offset");

      // Validar parámetros
      if ($page < 1) $page = 1;
      if ($limit < 1 || $limit > 100) $limit = 50;

      // CONSTRUIR SQL ÚNICA - PRODUCTOS Y COMBINACIONES SIN DUPLICADOS
      $searchTerm = '';
      $sql_where = 'p.active = 1';

      if (!empty($search)) {
        $searchTerm = pSQL($search);
        $sql_where .= " AND (pl.name LIKE '%$searchTerm%' OR p.reference LIKE '%$searchTerm%' OR pa.reference LIKE '%$searchTerm%' OR p.id_product = " . (int)$search . ")";
      }

      // SQL ÚNICA que obtiene productos base Y combinaciones
      $sql = 'SELECT SQL_CALC_FOUND_ROWS
                    p.id_product, 
                    COALESCE(pa.id_product_attribute, 0) as id_product_attribute,
                    CASE 
                        WHEN pa.id_product_attribute IS NULL THEN pl.name
                        ELSE CONCAT(pl.name, " - ", GROUP_CONCAT(DISTINCT CONCAT(agl.name, ": ", al.name) SEPARATOR ", "))
                    END as display_name,
                    p.reference,
                    COALESCE(pa.reference, "") as attribute_reference,
                    GROUP_CONCAT(DISTINCT cp.id_category) as categories,
                    (SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_attribute WHERE id_product = p.id_product) as combinations_count,
                    CASE 
                        WHEN pa.id_product_attribute IS NULL THEN "product"
                        ELSE "combination"
                    END as type
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                    p.id_product = pl.id_product 
                    AND pl.id_lang = ' . $id_lang . '
                    AND pl.id_shop = ' . $id_shop . '
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (
                    p.id_product = cp.id_product
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON (
                    p.id_product = pa.id_product
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON (
                    pa.id_product_attribute = pac.id_product_attribute
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON (
                    pac.id_attribute = a.id_attribute
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (
                    a.id_attribute = al.id_attribute AND al.id_lang = ' . $id_lang . '
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (
                    a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . $id_lang . '
                )
                WHERE ' . $sql_where . '
                GROUP BY p.id_product, pa.id_product_attribute
                ORDER BY pl.name ASC, pa.id_product_attribute ASC
                LIMIT ' . (int)$offset . ', ' . (int)$limit;

      $this->writeLog("Ejecutando SQL: " . $sql);

      // EJECUTAR CONSULTA DIRECTAMENTE
      $products = Db::getInstance()->executeS($sql);

      // VERIFICAR SI HUBO ERROR
      if ($products === false) {
        $error = Db::getInstance()->getMsgError();
        $this->writeLog('ERROR SQL: ' . $error, 'ERROR');
        throw new Exception('Error en consulta SQL: ' . $error);
      }

      // Asegurarse de que $products es un array
      if (!is_array($products)) {
        $products = [];
      }

      $totalProducts = (int)Db::getInstance()->getValue('SELECT FOUND_ROWS()');
      $totalPages = ceil($totalProducts / $limit);

      // Obtener configuraciones existentes
      $configurations = [];
      if (!empty($products)) {
        $configKeys = [];

        foreach ($products as $product) {
          if ($product['type'] == 'product') {
            $configKeys[] = 'product_' . $product['id_product'];
          } else {
            $configKeys[] = 'combination_' . $product['id_product'] . '_' . $product['id_product_attribute'];
          }
        }

        // Obtener todas las configuraciones de una sola vez
        $configs = Db::getInstance()->executeS(
          'SELECT id_product, id_product_attribute, stock_min, stock_display 
                 FROM ' . _DB_PREFIX_ . 'customstockdisplay 
                 WHERE id_shop = ' . $id_shop
        );

        foreach ($configs as $config) {
          $key = $config['id_product_attribute'] == 0 ?
            'product_' . $config['id_product'] :
            'combination_' . $config['id_product'] . '_' . $config['id_product_attribute'];
          $configurations[$key] = $config;
        }
      }

      $productsData = [];
      foreach ($products as $product) {
        $id_product = (int)$product['id_product'];
        $id_product_attribute = (int)$product['id_product_attribute'];

        $config_key = $product['type'] == 'product' ?
          'product_' . $id_product :
          'combination_' . $id_product . '_' . $id_product_attribute;

        $productsData[] = [
          'id_product' => $id_product,
          'id_product_attribute' => $id_product_attribute,
          'name' => $product['display_name'] ?: 'Product #' . $id_product,
          'reference' => $product['reference'] ?: 'N/A',
          'attribute_reference' => $product['attribute_reference'] ?: '',
          'categories' => $product['categories'] ? explode(',', $product['categories']) : [],
          'has_combinations' => ($product['combinations_count'] > 0),
          'type' => $product['type'],
          'stock_min' => $configurations[$config_key]['stock_min'] ?? 0,
          'stock_display' => $configurations[$config_key]['stock_display'] ?? 0,
        ];
      }

      // DEBUG: Ver qué productos encontramos
      $this->writeLog("=== PRODUCTOS ENCONTRADOS ===");
      foreach ($productsData as $product) {
        $this->writeLog("Producto: ID={$product['id_product']}, Attr={$product['id_product_attribute']}, Type={$product['type']}, Name={$product['name']}");
      }
      $this->writeLog("Total productos encontrados: " . count($productsData));

      echo json_encode([
        'success' => true,
        'products' => $productsData,
        'pagination' => [
          'current_page' => $page,
          'total_pages' => $totalPages,
          'total_products' => $totalProducts,
          'has_previous' => $page > 1,
          'has_next' => $page < $totalPages
        ]
      ]);
    } catch (Exception $e) {
      $this->writeLog('ERROR en ajaxProcessSearchProducts: ' . $e->getMessage(), 'ERROR');

      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
      ]);
    }

    exit;
  }

  public function ajaxProcessSaveConfiguration()
  {
    try {
      header('Content-Type: application/json');

      $productsData = Tools::getValue('products', []);
      $id_shop = (int)$this->context->shop->id;

      $this->writeLog("Procesando configuración via AJAX para " . count($productsData) . " productos modificados");
      $this->writeLog("Datos recibidos: " . print_r($productsData, true));

      $savedCount = 0;

      foreach ($productsData as $productJson) {
        $productData = json_decode($productJson, true);

        if (!is_array($productData)) {
          $this->writeLog("ERROR: Datos de producto inválidos: " . $productJson, 'ERROR');
          continue;
        }

        $id_product = (int)($productData['id_product'] ?? 0);
        $id_product_attribute = (int)($productData['id_product_attribute'] ?? 0);
        $stock_min = max(0, (int)($productData['stock_min'] ?? 0));
        $stock_display = max(0, (int)($productData['stock_display'] ?? 0));

        if ($id_product <= 0) {
          $this->writeLog("ERROR: ID producto inválido: $id_product", 'ERROR');
          continue;
        }

        $this->writeLog("Procesando: producto=$id_product, atributo=$id_product_attribute, min=$stock_min, display=$stock_display");

        $exists = Db::getInstance()->getValue(
          'SELECT id_customstock 
                 FROM ' . _DB_PREFIX_ . 'customstockdisplay 
                 WHERE id_product = ' . $id_product . ' 
                 AND id_product_attribute = ' . $id_product_attribute . '
                 AND id_shop = ' . $id_shop
        );

        $data = [
          'id_product' => $id_product,
          'id_product_attribute' => $id_product_attribute,
          'id_shop' => $id_shop,
          'stock_min' => $stock_min,
          'stock_display' => $stock_display,
          'date_upd' => date('Y-m-d H:i:s')
        ];

        if ($exists) {
          $result = Db::getInstance()->update(
            'customstockdisplay',
            $data,
            'id_product = ' . $id_product . ' 
                     AND id_product_attribute = ' . $id_product_attribute . ' 
                     AND id_shop = ' . $id_shop
          );
        } else {
          $data['date_add'] = date('Y-m-d H:i:s');
          $result = Db::getInstance()->insert('customstockdisplay', $data);
        }

        if ($result) {
          $savedCount++;
          $type = $id_product_attribute == 0 ? 'producto' : 'combinación';
          $this->writeLog("✅ $type $id_product" . ($id_product_attribute > 0 ? "_$id_product_attribute" : "") . " guardado - Mín: $stock_min, Mostrar: $stock_display");
        } else {
          $error = Db::getInstance()->getMsgError();
          $this->writeLog("❌ ERROR guardando producto $id_product (atributo $id_product_attribute): " . $error, 'ERROR');
        }
      }

      // Limpiar cache
      Tools::generateIndex();

      $this->writeLog("Configuración guardada correctamente: $savedCount elementos actualizados");

      echo json_encode([
        'success' => true,
        'message' => "Configuración guardada correctamente para $savedCount elementos",
        'saved_count' => $savedCount
      ]);
    } catch (Exception $e) {
      $this->writeLog('ERROR en ajaxProcessSaveConfiguration: ' . $e->getMessage(), 'ERROR');

      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
      ]);
    }

    exit;
  }


  public function hookDisplayHeader()
  {
    // Solo en páginas de producto
    if ($this->context->controller->php_self == 'product') {
      $id_product = (int)Tools::getValue('id_product');

      $this->writeLog("🎯 HOOK DisplayHeader ejecutado para producto: $id_product");

      if ($id_product) {
        // Verificar si este producto tiene configuraciones
        $hasConfig = (bool)Db::getInstance()->getValue(
          'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
                 WHERE id_product = ' . $id_product
        );

        $this->writeLog("📊 Producto $id_product tiene configuraciones: " . ($hasConfig ? 'SÍ' : 'NO'));

        if ($hasConfig) {
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

          $this->writeLog("✅ CSS/JS registrados para producto $id_product");
        }
      }
    }
    return '';
  }


  /**
   * HOOK: Modificar el product.tpl directamente - APPROACH DIRECTO
   */
  public function hookDisplayProductAdditionalInfo($params)
  {
    $id_product = (int)Tools::getValue('id_product');
    if (!$id_product) return '';

    $this->writeLog("=== HOOK DISPLAY PRODUCT ADDITIONAL INFO ===");
    $this->writeLog("Product ID: $id_product");

    // Obtener TODAS las configuraciones del producto (base + combinaciones)
    $configurations = Db::getInstance()->executeS(
      'SELECT id_product_attribute, stock_min, stock_display 
         FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
         WHERE id_product = ' . $id_product . '
         AND stock_display > 0'
    );

    if (empty($configurations)) {
      $this->writeLog("❌ No hay configuraciones para producto $id_product");
      return '';
    }

    // Preparar datos para JavaScript
    $stockConfig = [];
    foreach ($configurations as $config) {
      $key = (int)$config['id_product_attribute'];
      $stock_display = (int)$config['stock_display'];
      if ($stock_display > 0) {
        $stockConfig[$key] = [
          'min' => (int)$config['stock_min'],
          'display' => $stock_display,
          'displayValue' => '+' . $stock_display . ' uds'
        ];
      }
    }

    $stockConfigJson = Tools::jsonEncode($stockConfig);
    if ($stockConfigJson === false) return '';

    $this->writeLog("✅ Configuración enviada a JS: " . print_r($stockConfig, true));

    return "
        <script>
            window.stockDisplayConfig = $stockConfigJson;
            console.log('📦 CUSTOM STOCK: Configuración cargada:', window.stockDisplayConfig);
        </script>
    ";
  }

  /**
   * Hook: Añadir JavaScript al footer de producto (importante para cargar después del DOM)
   */
  // public function hookDisplayFooterProduct($params)
  // {
  //   $id_product = (int)Tools::getValue('id_product');
  //   if (!$id_product) return '';

  //   // Verificar si hay configuraciones
  //   $hasConfig = (bool)Db::getInstance()->getValue(
  //     'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
  //        WHERE id_product = ' . $id_product
  //   );

  //   if ($hasConfig) {
  //     return '
  //       <script>
  //           if (typeof window.stockDisplayConfig !== "undefined") {
  //               console.log("📦 CUSTOM STOCK: Inicializando desde footer...");
  //               // Aquí podrías inicializar tu JavaScript si lo necesitas
  //           }
  //       </script>';
  //   }

  //   return '';
  // }
}
