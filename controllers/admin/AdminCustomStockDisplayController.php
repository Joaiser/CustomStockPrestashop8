<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class AdminCustomStockDisplayController extends ModuleAdminController
{
  public function __construct()
  {
    // DEBUG INMEDIATO - Verificar que el controlador se carga
    $this->forceLog("🎯 CONSTRUCTOR AdminCustomStockDisplayController INICIADO");


    parent::__construct();

    // DEBUG después del parent
    $this->forceLog("🎯 CONSTRUCTOR COMPLETADO - Token: " . Tools::getAdminTokenLite('AdminCustomStockDisplay'));

    // No necesitamos listar aquí, solo AJAX
    $this->list_no_link = true;

    $this->forceLog("✅ CONSTRUCTOR COMPLETADO SIN ObjectModel");
  }

  public function init()
  {
    error_log("🎯 INIT AdminCustomStockDisplayController EJECUTADO");
    parent::init();
  }

  public function initContent()
  {
    $this->forceLog("🎯 INIT CONTENT INICIADO");

    parent::initContent();

    $this->forceLog("🎯 DESPUÉS DE PARENT INITCONTENT");

    // Obtener el módulo
    $module = Module::getInstanceByName('customstockdisplay');

    $this->forceLog("🎯 MÓDULO: " . ($module ? "ENCONTRADO" : "NO ENCONTRADO"));

    if ($module) {
      // ✅ CARGAR BOOTSTRAP MANUALMENTE
      $this->context->controller->addCSS(
        _PS_JS_DIR_ . 'jquery/plugins/bootstrap/css/bootstrap.min.css',
        'all',
        null,
        false
      );

      // ✅ Cargar Bootstrap JS si es necesario
      $this->context->controller->addJS(
        _PS_JS_DIR_ . 'jquery/plugins/bootstrap/js/bootstrap.min.js',
        false
      );

      // ✅ Cargar jQuery (por si acaso)
      $this->context->controller->addJquery();

      // REGISTRAR CSS ESPECÍFICO
      $this->context->controller->addCSS(
        $module->getPathUri() . 'views/css/registered-products.css',
        'all',
        null,
        false
      );

      $id_shop = (int)$this->context->shop->id;
      $registered_count = (int)Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
             WHERE id_shop = ' . $id_shop
      );

      // Ruta al template
      $template_path = _PS_MODULE_DIR_ . 'customstockdisplay/views/templates/admin/registered_products.tpl';
      $this->forceLog("🎯 TEMPLATE PATH: " . $template_path);
      $this->forceLog("🎯 TEMPLATE EXISTS: " . (file_exists($template_path) ? "SÍ" : "NO"));

      // Verificar si el template existe
      if (file_exists($template_path)) {
        error_log("✅ Template encontrado: " . $template_path);

        // Pasar variables al template
        $this->context->smarty->assign([
          'custom_stock_admin_url' => $this->context->link->getAdminLink('AdminCustomStockDisplay'),
          'custom_stock_admin_token' => Tools::getAdminTokenLite('AdminCustomStockDisplay'),
          'module_dir' => $module->getPathUri(),
          'admin_modules_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=customstockdisplay',
          'registered_count' => $registered_count,
          'displayName' => $module->displayName,
        ]);

        // Renderizar el template
        $this->content = $this->context->smarty->fetch($template_path);
      } else {
        // Template no encontrado - mostrar error
        $this->content = '
                <div class="alert alert-danger">
                    <h4>❌ Error: Template no encontrado</h4>
                    <p>El archivo no existe en: ' . $template_path . '</p>
                    <p>Por favor, verifica la ruta del template.</p>
                </div>
            ';
        error_log("❌ Template NO encontrado: " . $template_path);
      }
    } else {
      $this->content = '<div class="alert alert-danger">Error: Módulo customstockdisplay no encontrado</div>';
    }

    $this->context->smarty->assign('content', $this->content);
    error_log("✅ InitContent completado");
  }

  /**
   * Obtener productos registrados con filtros
   */
  public function ajaxProcessGetRegisteredProducts()
  {
    try {
      $this->writeLog("=== AJAX GetRegisteredProducts llamado ===");

      // Añadir headers para JSON
      header('Content-Type: application/json');

      $page = (int)Tools::getValue('page', 1);
      $limit = (int)Tools::getValue('limit', 50);
      $search = Tools::getValue('search', '');
      $id_shop = (int)$this->context->shop->id;

      $offset = ($page - 1) * $limit;

      // Construir WHERE clause
      $where_conditions = ['csd.id_shop = ' . $id_shop];

      if (!empty($search)) {
        $search = pSQL($search);
        $where_conditions[] = "(
                    pl.name LIKE '%{$search}%' OR 
                    p.reference LIKE '%{$search}%' OR 
                    pa.reference LIKE '%{$search}%' OR
                    p.id_product = " . (int)$search . "
                )";
      }

      $where = implode(' AND ', $where_conditions);

      // Consulta principal
      $sql = "SELECT SQL_CALC_FOUND_ROWS
                        csd.id_customstock,
                        csd.id_product,
                        csd.id_product_attribute,
                        csd.stock_min,
                        csd.stock_display,
                        csd.date_add,
                        csd.date_upd,
                        pl.name as product_name,
                        p.reference as product_reference,
                        COALESCE(pa.reference, '') as attribute_reference,
                        CASE 
                            WHEN csd.id_product_attribute = 0 THEN 'product'
                            ELSE 'combination'
                        END as type,
                        GROUP_CONCAT(DISTINCT CONCAT(agl.name, ': ', al.name)) as attribute_names
                    FROM `" . _DB_PREFIX_ . "customstockdisplay` csd
                    INNER JOIN `" . _DB_PREFIX_ . "product` p ON (csd.id_product = p.id_product)
                    INNER JOIN `" . _DB_PREFIX_ . "product_lang` pl ON (
                        p.id_product = pl.id_product 
                        AND pl.id_lang = " . (int)$this->context->language->id . "
                        AND pl.id_shop = " . $id_shop . "
                    )
                    LEFT JOIN `" . _DB_PREFIX_ . "product_attribute` pa ON (
                        csd.id_product_attribute = pa.id_product_attribute 
                        AND pa.id_product = p.id_product
                    )
                    LEFT JOIN `" . _DB_PREFIX_ . "product_attribute_combination` pac ON (
                        pa.id_product_attribute = pac.id_product_attribute
                    )
                    LEFT JOIN `" . _DB_PREFIX_ . "attribute` a ON (
                        pac.id_attribute = a.id_attribute
                    )
                    LEFT JOIN `" . _DB_PREFIX_ . "attribute_lang` al ON (
                        a.id_attribute = al.id_attribute 
                        AND al.id_lang = " . (int)$this->context->language->id . "
                    )
                    LEFT JOIN `" . _DB_PREFIX_ . "attribute_group_lang` agl ON (
                        a.id_attribute_group = agl.id_attribute_group 
                        AND agl.id_lang = " . (int)$this->context->language->id . "
                    )
                    WHERE {$where}
                    GROUP BY csd.id_customstock
                    ORDER BY pl.name ASC, csd.id_product_attribute ASC
                    LIMIT " . (int)$offset . ", " . (int)$limit;

      $products = Db::getInstance()->executeS($sql);
      $total = (int)Db::getInstance()->getValue('SELECT FOUND_ROWS()');
      $total_pages = ceil($total / $limit);

      // Formatear respuesta
      $formatted_products = [];
      foreach ($products as $product) {
        $display_name = $product['product_name'];
        if ($product['type'] == 'combination' && !empty($product['attribute_names'])) {
          $display_name .= ' - ' . $product['attribute_names'];
        }

        $formatted_products[] = [
          'id_customstock' => (int)$product['id_customstock'],
          'id_product' => (int)$product['id_product'],
          'id_product_attribute' => (int)$product['id_product_attribute'],
          'display_name' => $display_name,
          'product_reference' => $product['product_reference'],
          'attribute_reference' => $product['attribute_reference'],
          'type' => $product['type'],
          'stock_min' => (int)$product['stock_min'],
          'stock_display' => (int)$product['stock_display'],
          'date_add' => $product['date_add'],
          'date_upd' => $product['date_upd']
        ];
      }

      die(json_encode([
        'success' => true,
        'products' => $formatted_products,
        'pagination' => [
          'current_page' => $page,
          'total_pages' => $total_pages,
          'total_products' => $total,
          'has_previous' => $page > 1,
          'has_next' => $page < $total_pages
        ]
      ]));
    } catch (Exception $e) {
      die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]));
    }
  }

  /**
   * Eliminar configuración individual
   */
  public function ajaxProcessDeleteConfiguration()
  {
    try {
      $id_customstock = (int)Tools::getValue('id_customstock');
      $id_shop = (int)$this->context->shop->id;

      if ($id_customstock <= 0) {
        throw new Exception('ID inválido');
      }

      // Verificar que existe y pertenece a esta shop
      $exists = Db::getInstance()->getValue(
        '
                SELECT id_customstock 
                FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
                WHERE id_customstock = ' . $id_customstock . ' 
                AND id_shop = ' . $id_shop
      );

      if (!$exists) {
        throw new Exception('Configuración no encontrada');
      }

      $result = Db::getInstance()->delete(
        'customstockdisplay',
        'id_customstock = ' . $id_customstock . ' AND id_shop = ' . $id_shop
      );

      if ($result) {
        // Limpiar cache
        Tools::generateIndex();

        die(json_encode([
          'success' => true,
          'message' => 'Configuración eliminada correctamente'
        ]));
      } else {
        throw new Exception('Error al eliminar la configuración');
      }
    } catch (Exception $e) {
      die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]));
    }
  }

  /**
   * Eliminación masiva
   */
  public function ajaxProcessBulkDelete()
  {
    try {
      // Para FormData con 'ids[]'
      $ids = Tools::getValue('ids');

      if (empty($ids) || !is_array($ids)) {
        throw new Exception('No se seleccionaron configuraciones');
      }

      // Sanitizar IDs
      $ids = array_map('intval', $ids);
      $ids = array_filter($ids);

      if (empty($ids)) {
        throw new Exception('IDs inválidos');
      }

      $id_shop = (int)$this->context->shop->id;

      $result = Db::getInstance()->delete(
        'customstockdisplay',
        'id_customstock IN (' . implode(',', $ids) . ') AND id_shop = ' . $id_shop
      );

      if ($result) {
        // Limpiar cache
        Tools::generateIndex();

        die(json_encode([
          'success' => true,
          'message' => 'Se eliminaron ' . $result . ' configuraciones'
        ]));
      } else {
        throw new Exception('Error al eliminar las configuraciones');
      }
    } catch (Exception $e) {
      die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]));
    }
  }

  /*
  * Actualizar confi individual
  */
  public function ajaxProcessUpdateConfiguration()
  {
    try {
      header('Content-Type: application/json');

      $id_customstock = (int)Tools::getValue('id_customstock');
      $stock_min = (int)Tools::getValue('stock_min');
      $stock_display = (int)Tools::getValue('stock_display');
      $id_shop = (int)$this->context->shop->id;

      if ($id_customstock <= 0) {
        throw new Exception('ID inválido');
      }

      if ($stock_min < 0 || $stock_display < 0) {
        throw new Exception('Los valores de stock deben ser positivos');
      }

      // Verificar que existe y pertenece a esta shop
      $exists = Db::getInstance()->getValue(
        'SELECT id_customstock 
             FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
             WHERE id_customstock = ' . $id_customstock . ' 
             AND id_shop = ' . $id_shop
      );

      if (!$exists) {
        throw new Exception('Configuración no encontrada');
      }

      // Actualizar la configuración
      $data = [
        'stock_min' => $stock_min,
        'stock_display' => $stock_display,
        'date_upd' => date('Y-m-d H:i:s')
      ];

      $result = Db::getInstance()->update(
        'customstockdisplay',
        $data,
        'id_customstock = ' . $id_customstock . ' AND id_shop = ' . $id_shop
      );

      if ($result) {
        // Limpiar cache
        Tools::generateIndex();

        die(json_encode([
          'success' => true,
          'message' => 'Configuración actualizada correctamente'
        ]));
      } else {
        throw new Exception('Error al actualizar la configuración');
      }
    } catch (Exception $e) {
      die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]));
    }
  }

  private function writeLog($message, $level = 'INFO')
  {
    $logFile = _PS_MODULE_DIR_ . 'customstockdisplay/logs/customstockdisplay.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
  }

  private function forceLog($message)
  {
    $logFile = _PS_MODULE_DIR_ . 'customstockdisplay/logs/debug_controller.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] 🔥 $message\n";

    // Forzar escritura sin importar permisos
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log("CUSTOM_STOCK_DEBUG: " . $message); // También al log de PHP
  }
}
