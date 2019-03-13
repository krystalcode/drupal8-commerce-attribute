<?php

namespace Drupal\commerce_attributes\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\commerce\Entity\CommerceBundleEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\commerce_product\Entity\ProductAttributeInterface as BaseProductAttributeInterface;

/**
 * Defines the interface for product attributes.
 */
interface ProductAttributeInterface extends CommerceBundleEntityInterface, BaseProductAttributeInterface {

}
