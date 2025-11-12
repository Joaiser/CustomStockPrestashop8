<?php

class CustomStockHookHandler
{
  private $module;
  private $service;
  private $logger;
  private $modulePath;

  public function __construct($module)
  {
    $this->module = $module;
    $this->service = new CustomStockService($module);
    $this->logger = new CustomStockLogger($module);
    $this->modulePath = $module->getLocalPath(); // CORRECCIÓN: usar getLocalPath()
  }

  public function handleDisplayHeader()
  {
    if (Context::getContext()->controller->php_self == 'product') {
      $id_product = (int)Tools::getValue('id_product');

      if ($id_product && $this->service->shouldLoadFrontendAssets($id_product)) {
        Context::getContext()->controller->registerJavascript(
          'module-customstockdisplay-front',
          'modules/' . $this->module->name . '/views/js/stock-display-front.js',
          ['position' => 'bottom', 'priority' => 150]
        );

        Context::getContext()->controller->registerStylesheet(
          'customstockdisplay-css',
          'modules/' . $this->module->name . '/views/css/customstockdisplay.css',
          ['position' => 'head', 'priority' => 150]
        );

        $this->logger->info("✅ CSS/JS registrados para producto $id_product");
      }
    }
    return '';
  }

  public function handleDisplayProductAdditionalInfo($params)
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

  public function handleDisplayFooterProduct($params)
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

  public function handleDisplayBackOfficeHeader()
  {
    $controller = Tools::getValue('controller');
    $configure = Tools::getValue('configure');

    if ($controller === 'AdminModules' && $configure === $this->module->name) {
      Context::getContext()->controller->addJS($this->modulePath . 'views/js/admin.js');
    }

    return '';
  }
}
