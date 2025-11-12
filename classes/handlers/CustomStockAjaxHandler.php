<?php

class CustomStockAjaxHandler
{
  private $module;
  private $searchService;
  private $repository;
  private $logger;

  public function __construct($module)
  {
    $this->module = $module;
    $this->searchService = new CustomStockSearchService($module);
    $this->repository = new CustomStockRepository($module);
    $this->logger = new CustomStockLogger($module);
  }

  public function handleSearchProducts()
  {
    try {
      $search = Tools::getValue('search', '');
      $page = max(1, (int)Tools::getValue('page', 1));
      $limit = min(max(1, (int)Tools::getValue('limit', 50)), 100);
      $id_lang = (int)Context::getContext()->language->id;
      $id_shop = (int)Context::getContext()->shop->id;

      $productsData = $this->searchService->searchProducts($search, $page, $limit, $id_lang, $id_shop);

      echo json_encode([
        'success' => true,
        'products' => $productsData['products'],
        'pagination' => $productsData['pagination']
      ]);
    } catch (Exception $e) {
      $this->logger->error('ERROR en handleSearchProducts: ' . $e->getMessage());
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
      ]);
    }
    exit;
  }

  public function handleSaveConfiguration()
  {
    try {
      $productsData = Tools::getValue('products', []);
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
          $result = $this->repository->saveConfiguration(
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
      $this->logger->error('ERROR en handleSaveConfiguration: ' . $e->getMessage());
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
      ]);
    }
    exit;
  }
}
