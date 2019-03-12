<?php

namespace Drupal\commerce_attributes\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\commerce\Entity\CommerceBundleEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;

/**
 * Defines the interface for product attributes.
 */
interface ProductAttributeInterface extends CommerceBundleEntityInterface {

  /**
   * Gets the attribute values.
   *
   * @return \Drupal\commerce_product\Entity\ProductAttributeValueInterface[]
   *   The attribute values.
   */
  public function getValues();

  /**
   * Gets the attribute element type.
   *
   * @return string
   *   The element type name.
   */
  public function getElementType();

}
