<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class CustomStockService
{
  private $module;
  private $repository;

  public function __construct($module)
  {
    $this->module = $module;
    $this->repository = new CustomStockRepository($module);
  }

  public function getStockConfigForProduct($id_product)
  {
    $configurations = $this->repository->getProductConfigurations($id_product);

    if (empty($configurations)) {
      return [];
    }

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

    return $stockConfig;
  }

  public function shouldLoadFrontendAssets($id_product)
  {
    $configurations = $this->repository->getProductConfigurations($id_product);
    return !empty($configurations);
  }

  public function getRegisteredCount()
  {
    return $this->repository->getRegisteredProductsCount();
  }
}
