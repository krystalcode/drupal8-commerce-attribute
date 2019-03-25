<?php

namespace Drupal\commerce_attribute\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityInterface;
use Drupal\commerce_product\Entity\ProductAttributeInterface as BaseProductAttributeInterface;

/**
 * Defines the interface for product attributes.
 */
interface ProductAttributeInterface extends CommerceBundleEntityInterface, BaseProductAttributeInterface {

}
