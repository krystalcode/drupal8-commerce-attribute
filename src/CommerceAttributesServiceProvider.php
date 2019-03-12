<?php

namespace Drupal\commerce_attributes;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

// @note: You only need Reference, if you want to change service arguments.
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the commerce_product.attribute_field_manager service.
 */
class CommerceAttributesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('commerce_product.attribute_field_manager');
    $definition->setClass('Drupal\commerce_attributes\ProductAttributeFieldManager');
  }
}
