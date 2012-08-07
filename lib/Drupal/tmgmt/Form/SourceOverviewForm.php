<?php

/**
 * @file
 * Contains \Drupal\tmgmt\Controller\SourceOverviewForm.
 */

namespace Drupal\tmgmt\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Source overview form.
 */
class SourceOverviewForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'tmgmt_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $plugin = NULL, $item_type = NULL) {
    var_dump($plugin);
    var_dump($item_type);
    $source = \Drupal::service('plugin.manager.tmgmt.source')->createInstance($plugin);
    $definition = \Drupal::service('plugin.manager.tmgmt.source')->getDefinition($plugin);

    $form['#title'] = $this->t('@type overview (@plugin)', array('@type' => $source->getItemTypeLabel($item_type), '@plugin' => $definition['label']));
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Request translation'),
    );

    $source_ui = \Drupal::service('plugin.manager.tmgmt.source')->createUIInstance($plugin);
    $form_state['plugin'] = $plugin;
    $form_state['item_type'] = $item_type;
    return $source_ui->overviewForm($form, $form_state, $item_type);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    // Copy the form state so we are not removing important information from it
    // when sending it through form_state_values_clean().
    $cleaned = $form_state;
    form_state_values_clean($cleaned);
    if (empty($cleaned['values'])) {
      form_set_error('items', t("You didn't select any source objects"));
    }
    list($plugin, $item_type) = $form_state['build_info']['args'];
    // Execute the validation method on the source plugin controller.
    $source_ui = \Drupal::service('plugin.manager.tmgmt.source')->createUIInstance($plugin);
    $source_ui->overviewFormValidate($form, $form_state, $item_type);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    list($plugin, $item_type) = $form_state['build_info']['args'];
    // Execute the submit method on the source plugin controller.
    $source_ui = \Drupal::service('plugin.manager.tmgmt.source')->createUIInstance($plugin);
    $source_ui->overviewFormSubmit($form, $form_state, $item_type);
  }

}

