<?php

namespace Drupal\commerce_attribute\Form;

use Drupal\commerce\EntityHelper;
use Drupal\commerce\EntityTraitManagerInterface;
use Drupal\commerce\Form\CommerceBundleEntityFormBase;
use Drupal\commerce\InlineFormManager;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Defines the add/edit/duplicate form for product attribute.
 */
class ProductAttributeForm extends CommerceBundleEntityFormBase {

  /**
   * The attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * Constructs a new ProductAttributeForm object.
   *
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $attribute_field_manager
   *   The attribute field manager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\commerce\EntityTraitManagerInterface $trait_manager
   *   The trait manager.
   */
  public function __construct(ProductAttributeFieldManagerInterface $attribute_field_manager, InlineFormManager $inline_form_manager, EntityTraitManagerInterface $trait_manager) {
    $this->attributeFieldManager = $attribute_field_manager;
    $this->inlineFormManager = $inline_form_manager;
    parent::__construct($trait_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_product.attribute_field_manager'),
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('plugin.manager.commerce_entity_trait')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_attribute\Entity\ProductAttributeInterface $attribute */
    $attribute = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $attribute->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $attribute->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_product\Entity\ProductAttribute::load',
      ],
      // Attribute field names are constructed as 'attribute_' + id, and must
      // not be longer than 32 characters. Account for that prefix length here.
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH - 10,
      '#disabled' => !$attribute->isNew(),
    ];

    // Add in the trait form.
    $form = $this->buildTraitForm($form, $form_state);

    $form['elementType'] = [
      '#type' => 'select',
      '#title' => $this->t('Element type'),
      '#description' => $this->t('Controls how the attribute is displayed on the add to cart form.'),
      '#options' => [
        'radios' => $this->t('Radio buttons'),
        'select' => $this->t('Select list'),
        'commerce_product_rendered_attribute' => $this->t('Rendered attribute'),
      ],
      '#default_value' => $attribute->getElementType(),
    ];

    $attribute_field_map = $this->attributeFieldManager->getFieldMap();
    $variation_type_storage = $this->entityTypeManager->getStorage('commerce_product_variation_type');
    $variation_types = $variation_type_storage->loadMultiple();
    // Allow the attribute to be assigned to a product variation type.
    $form['original_variation_types'] = [
      '#type' => 'value',
      '#value' => [],
    ];
    $form['variation_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Product variation types'),
      '#options' => EntityHelper::extractLabels($variation_types),
      '#access' => count($variation_types) > 0,
    ];
    $disabled_variation_types = [];
    foreach ($variation_types as $variation_type_id => $variation_type) {
      if (!$attribute->isNew() && isset($attribute_field_map[$variation_type_id])) {
        $used_attributes = array_column($attribute_field_map[$variation_type_id], 'attribute_id');
        if (in_array($attribute->id(), $used_attributes)) {
          $form['original_variation_types']['#value'][$variation_type_id] = $variation_type_id;
          $form['variation_types']['#default_value'][$variation_type_id] = $variation_type_id;
          if (!$this->attributeFieldManager->canDeleteField($attribute, $variation_type_id)) {
            $form['variation_types'][$variation_type_id] = [
              '#disabled' => TRUE,
            ];
            $disabled_variation_types[] = $variation_type_id;
          }
        }
      }
    }
    $form['disabled_variation_types'] = [
      '#type' => 'value',
      '#value' => $disabled_variation_types,
    ];

    if ($this->moduleHandler->moduleExists('content_translation')) {
      $enabled = TRUE;
      if (!$attribute->isNew()) {
        $translation_manager = \Drupal::service('content_translation.manager');
        $enabled = $translation_manager->isEnabled('commerce_product_attribute_value', $attribute->id());
      }
      $form['enable_value_translation'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable attribute value translation'),
        '#default_value' => $enabled,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTraitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = $this->entity->save();

    // Process traits.
    $this->submitTraitForm($form, $form_state);

    $original_variation_types = $form_state->getValue('original_variation_types', []);
    $variation_types = array_filter($form_state->getValue('variation_types', []));
    $disabled_variation_types = $form_state->getValue('disabled_variation_types', []);
    $variation_types = array_unique(array_merge($disabled_variation_types, $variation_types));
    $selected_variation_types = array_diff($variation_types, $original_variation_types);
    $unselected_variation_types = array_diff($original_variation_types, $variation_types);
    if ($selected_variation_types) {
      foreach ($selected_variation_types as $selected_variation_type) {
        $this->attributeFieldManager->createField($this->entity, $selected_variation_type);
      }
    }
    if ($unselected_variation_types) {
      foreach ($unselected_variation_types as $unselected_variation_type) {
        $this->attributeFieldManager->deleteField($this->entity, $unselected_variation_type);
      }
    }

    if ($this->moduleHandler->moduleExists('content_translation')) {
      $translation_manager = \Drupal::service('content_translation.manager');
      // Logic from content_translation_language_configuration_element_submit().
      $enabled = $form_state->getValue('enable_value_translation');
      if ($translation_manager->isEnabled('commerce_product_attribute_value', $this->entity->id()) != $enabled) {
        $translation_manager->setEnabled('commerce_product_attribute_value', $this->entity->id(), $enabled);
        $this->entityTypeManager->clearCachedDefinitions();
        \Drupal::service('router.builder')->setRebuildNeeded();
      }
    }

    if ($status == SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created the %label product attribute.', ['%label' => $this->entity->label()]));
      // Send the user to the edit form to create the attribute values.
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
    else {
      $this->messenger()->addMessage($this->t('Updated the %label product attribute.', ['%label' => $this->entity->label()]));
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    }
  }

}
