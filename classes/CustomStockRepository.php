<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class CustomStockRepository
{
  private $module;

  public function __construct($module)
  {
    $this->module = $module;
  }

  public function getProductConfigurations($id_product, $id_shop = null)
  {
    if ($id_shop === null) {
      $id_shop = Context::getContext()->shop->id;
    }

    $sql = 'SELECT id_product_attribute, stock_min, stock_display 
                FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
                WHERE id_product = ' . (int)$id_product . '
                AND id_shop = ' . (int)$id_shop . '
                AND stock_display > 0';

    return Db::getInstance()->executeS($sql);
  }

  public function getRegisteredProductsCount($id_shop = null)
  {
    if ($id_shop === null) {
      $id_shop = Context::getContext()->shop->id;
    }

    return (int)Db::getInstance()->getValue(
      'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customstockdisplay` 
             WHERE id_shop = ' . (int)$id_shop
    );
  }

  public function saveConfiguration($id_product, $id_product_attribute, $stock_min, $stock_display, $id_shop = null)
  {
    if ($id_shop === null) {
      $id_shop = Context::getContext()->shop->id;
    }

    $exists = Db::getInstance()->getValue(
      'SELECT id_customstock 
             FROM ' . _DB_PREFIX_ . 'customstockdisplay 
             WHERE id_product = ' . (int)$id_product . ' 
             AND id_product_attribute = ' . (int)$id_product_attribute . '
             AND id_shop = ' . (int)$id_shop
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
      return Db::getInstance()->update(
        'customstockdisplay',
        $data,
        'id_product = ' . (int)$id_product . ' 
                AND id_product_attribute = ' . (int)$id_product_attribute . ' 
                AND id_shop = ' . (int)$id_shop
      );
    } else {
      $data['date_add'] = date('Y-m-d H:i:s');
      return Db::getInstance()->insert('customstockdisplay', $data);
    }
  }

  public function deleteConfiguration($id_customstock, $id_shop = null)
  {
    if ($id_shop === null) {
      $id_shop = Context::getContext()->shop->id;
    }

    return Db::getInstance()->delete(
      'customstockdisplay',
      'id_customstock = ' . (int)$id_customstock . ' AND id_shop = ' . (int)$id_shop
    );
  }

  public function bulkDeleteConfigurations($ids, $id_shop = null)
  {
    if ($id_shop === null) {
      $id_shop = Context::getContext()->shop->id;
    }

    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);

    if (empty($ids)) {
      return false;
    }

    return Db::getInstance()->delete(
      'customstockdisplay',
      'id_customstock IN (' . implode(',', $ids) . ') AND id_shop = ' . (int)$id_shop
    );
  }
}
