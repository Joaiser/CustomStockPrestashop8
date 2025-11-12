<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class CustomStockSearchService
{
  private $module;
  private $repository;

  public function __construct($module)
  {
    $this->module = $module;
    $this->repository = new CustomStockRepository($module);
  }

  /**
   * Search products with pagination and filters
   */
  public function searchProducts($search, $page, $limit, $id_lang, $id_shop)
  {
    $offset = ($page - 1) * $limit;

    $sql = $this->buildSearchQuery($search, $id_lang, $id_shop);
    $sql .= " LIMIT " . (int)$offset . ", " . (int)$limit;

    $products = Db::getInstance()->executeS($sql);
    $totalProducts = (int)Db::getInstance()->getValue('SELECT FOUND_ROWS()');
    $totalPages = ceil($totalProducts / $limit);

    $configurations = $this->getConfigurationsForSearchResults($products, $id_shop);
    $formattedProducts = $this->formatSearchResults($products, $configurations);

    return [
      'products' => $formattedProducts,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_products' => $totalProducts,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages
      ]
    ];
  }

  /**
   * Get products for admin page with pagination
   */
  public function getProductsForAdminPage($page, $limit, $id_lang, $id_shop)
  {
    $offset = ($page - 1) * $limit;

    $sql = $this->buildProductsQuery($id_lang, $id_shop);
    $sql .= " LIMIT " . (int)$offset . ", " . (int)$limit;

    $products = Db::getInstance()->executeS($sql);
    $totalProducts = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` WHERE active = 1');
    $totalPages = ceil($totalProducts / $limit);

    $configurations = $this->getConfigurationsForProducts($products, $id_shop);
    $formattedProducts = $this->formatAdminProducts($products, $configurations);

    return [
      'products' => $formattedProducts,
      'total_pages' => $totalPages
    ];
  }

  /**
   * Build search query for products and combinations
   */
  private function buildSearchQuery($search, $id_lang, $id_shop)
  {
    $sql_where = 'p.active = 1';

    if (!empty($search)) {
      $searchTerm = pSQL($search);
      $sql_where .= " AND (pl.name LIKE '%$searchTerm%' OR p.reference LIKE '%$searchTerm%' OR pa.reference LIKE '%$searchTerm%' OR p.id_product = " . (int)$search . ")";
    }

    return 'SELECT SQL_CALC_FOUND_ROWS
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
                LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (p.id_product = cp.id_product)
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON (p.id_product = pa.id_product)
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON (pa.id_product_attribute = pac.id_product_attribute)
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON (pac.id_attribute = a.id_attribute)
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.id_attribute = al.id_attribute AND al.id_lang = ' . $id_lang . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . $id_lang . ')
                WHERE ' . $sql_where . '
                GROUP BY p.id_product, pa.id_product_attribute
                ORDER BY pl.name ASC, pa.id_product_attribute ASC';
  }

  /**
   * Build query for admin products list
   */
  private function buildProductsQuery($id_lang, $id_shop)
  {
    return 'SELECT 
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
                LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (p.id_product = cp.id_product)
                WHERE p.active = 1
                GROUP BY p.id_product
                ORDER BY pl.name ASC';
  }

  /**
   * Format search results with configurations
   */
  private function formatSearchResults($products, $configurations)
  {
    $formattedProducts = [];

    foreach ($products as $product) {
      $id_product = (int)$product['id_product'];
      $id_product_attribute = (int)$product['id_product_attribute'];

      $config_key = $product['type'] == 'product' ?
        'product_' . $id_product :
        'combination_' . $id_product . '_' . $id_product_attribute;

      $formattedProducts[] = [
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

    return $formattedProducts;
  }

  /**
   * Format admin products list
   */
  private function formatAdminProducts($products, $configurations)
  {
    $formattedProducts = [];

    foreach ($products as $product) {
      $id_product = (int)$product['id_product'];

      $formattedProducts[] = [
        'id_product' => $id_product,
        'name' => $product['name'] ?: 'Product #' . $id_product,
        'reference' => $product['reference'] ?: 'N/A',
        'categories' => $product['categories'] ? explode(',', $product['categories']) : [],
        'has_combinations' => ($product['combinations_count'] > 0),
        'stock_min' => $configurations[$id_product]['stock_min'] ?? 0,
        'stock_display' => $configurations[$id_product]['stock_display'] ?? 0,
      ];
    }

    return $formattedProducts;
  }

  /**
   * Get configurations for search results (products + combinations)
   */
  private function getConfigurationsForSearchResults($products, $id_shop)
  {
    if (empty($products)) {
      return [];
    }

    $configs = Db::getInstance()->executeS(
      'SELECT id_product, id_product_attribute, stock_min, stock_display 
             FROM ' . _DB_PREFIX_ . 'customstockdisplay 
             WHERE id_shop = ' . $id_shop
    );

    $configurations = [];
    foreach ($configs as $config) {
      $key = $config['id_product_attribute'] == 0 ?
        'product_' . $config['id_product'] :
        'combination_' . $config['id_product'] . '_' . $config['id_product_attribute'];
      $configurations[$key] = $config;
    }

    return $configurations;
  }

  /**
   * Get configurations for simple products list
   */
  private function getConfigurationsForProducts($products, $id_shop)
  {
    if (empty($products)) {
      return [];
    }

    $productIds = array_map(function ($product) {
      return (int)$product['id_product'];
    }, $products);

    $configs = Db::getInstance()->executeS(
      'SELECT id_product, stock_min, stock_display 
             FROM ' . _DB_PREFIX_ . 'customstockdisplay 
             WHERE id_product IN (' . implode(',', $productIds) . ')
             AND id_shop = ' . $id_shop
    );

    $configurations = [];
    foreach ($configs as $config) {
      $configurations[$config['id_product']] = $config;
    }

    return $configurations;
  }
}
