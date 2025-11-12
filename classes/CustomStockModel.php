<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class CustomStockModel extends ObjectModel
{
  public $id_customstock;
  public $id_product;
  public $id_product_attribute;
  public $id_shop;
  public $stock_min;
  public $stock_display;
  public $date_add;
  public $date_upd;

  public static $definition = [
    'table' => 'customstockdisplay',
    'primary' => 'id_customstock',
    'fields' => [
      'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
      'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
      'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
      'stock_min' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
      'stock_display' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
      'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
      'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
    ],
  ];
}
