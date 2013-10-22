<?php

/**
 * @file
 * Contains \Drupal\tmgmt\Entity\Form\JobFormController.
 */

namespace Drupal\tmgmt\Entity\Form;

use Drupal\tmgmt\Entity\Job;
use Drupal\views\Entity\View;

/**
 * Form controller for the job edit forms.
 *
 * @ingroup tmgmt_job
 */
class JobFormController extends TmgmtFormControllerBase {

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);
    $job = $this->entity;
    // Handle source language.
    $available['source_language'] = tmgmt_available_languages();
    $job->source_language = isset($form_state['values']['source_language']) ? $form_state['values']['source_language'] : $job->source_language;

    // Handle target language.
    $available['target_language'] = tmgmt_available_languages();
    $job->target_language = isset($form_state['values']['target_language']) ? $form_state['values']['target_language'] : $job->target_language;

    // Remove impossible combinations so we don't end up with the same source and
    // target language in the dropdowns.
    foreach (array('source_language' => 'target_language', 'target_language' => 'source_language') as $key => $opposite) {
      if (!empty($job->{$key})) {
        unset($available[$opposite][$job->{$key}]);
      }
    }

    $source = tmgmt_language_label($job->source_language) ?: '?';
    if (empty($job->target_language)) {
      $job->target_language = key($available['target_language']);
      $target = '?';
    }
    else {
      $target = tmgmt_language_label($job->target_language);
    }

    $states = tmgmt_job_states();
    // Set the title of the page to the label and the current state of the job.
    drupal_set_title(t('@title (@source to @target, @state)', array(
      '@title' => $job->label(),
      '@source' => $source,
      '@target' => $target,
      '@state' => $states[$job->state],
    )));

    $form['info'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('tmgmt-ui-job-info', 'clearfix')),
      '#weight' => 0,
    );

    // Check for label value and set for dynamically change.
    if (isset($form_state['values']['label']) && $form_state['values']['label'] == $job->label()) {
      $job->label = NULL;
      $form_state['values']['label'] = $job->label = $job->label();
    }
    $form['info']['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#description' => t('You can provide a label for this job in order to identify it easily later on. Or leave it empty to use default one.'),
      '#default_value' => $job->label(),
      '#prefix' => '<div id="tmgmt-ui-label">',
      '#suffix' => '</div>',
    );

    // Make the source and target language flexible by showing either a select
    // dropdown or the plain string (if preselected).
    if (!empty($job->source_language) || !$job->isSubmittable()) {
      $form['info']['source_language'] = array(
        '#title' => t('Source language'),
        '#type' =>  'item',
        '#markup' => isset($available['source_language'][$job->source_language]) ? $available['source_language'][$job->source_language] : '',
        '#prefix' => '<div id="tmgmt-ui-source-language" class="tmgmt-ui-source-language tmgmt-ui-info-item">',
        '#suffix' => '</div>',
        '#value' => $job->source_language,
      );
    }
    else {
      $form['info']['source_language'] = array(
        '#title' => t('Source language'),
        '#type' => 'select',
        '#options' => $available['source_language'],
        '#default_value' => $job->source_language,
        '#required' => TRUE,
        '#prefix' => '<div id="tmgmt-ui-source-language" class="tmgmt-ui-source-language tmgmt-ui-info-item">',
        '#suffix' => '</div>',
        '#ajax' => array(
          'callback' => array($this, 'ajaxLanguageSelect'),
        ),
      );
    }
    if (!$job->isSubmittable()) {
      $form['info']['target_language'] = array(
        '#title' => t('Target language'),
        '#type' => 'item',
        '#markup' => isset($available['target_language'][$job->target_language]) ? $available['target_language'][$job->target_language] : '',
        '#prefix' => '<div id="tmgmt-ui-target-language" class="tmgmt-ui-target-language tmgmt-ui-info-item">',
        '#suffix' => '</div>',
        '#value' => $job->target_language,
      );
    }
    else {
      $form['info']['target_language'] = array(
        '#title' => t('Target language'),
        '#type' => 'select',
        '#options' => $available['target_language'],
        '#default_value' => $job->target_language,
        '#required' => TRUE,
        '#prefix' => '<div id="tmgmt-ui-target-language" class="tmgmt-ui-target-language tmgmt-ui-info-item">',
        '#suffix' => '</div>',
        '#ajax' => array(
          'callback' => array($this, 'ajaxLanguageSelect'),
          'wrapper' => 'tmgmt-ui-target-language',
        ),
      );
    }

    // Display selected translator for already submitted jobs.
    if (!$job->isSubmittable()) {
      $translators = tmgmt_translator_labels();
      $form['info']['translator'] = array(
        '#type' => 'item',
        '#title' => t('Translator'),
        '#markup' => isset($translators[$job->translator]) ? check_plain($translators[$job->translator]) : t('Missing translator'),
        '#prefix' => '<div class="tmgmt-ui-translator tmgmt-ui-info-item">',
        '#suffix' => '</div>',
        '#value' => $job->translator,
      );
    }

    $form['info']['word_count'] = array(
      '#type' => 'item',
      '#title' => t('Total word count'),
      '#markup' => number_format($job->getWordCount()),
      '#prefix' => '<div class="tmgmt-ui-word-count tmgmt-ui-info-item">',
      '#suffix' => '</div>',
    );

    // Display created time only for jobs that are not new anymore.
    if (!$job->isUnprocessed()) {
      $form['info']['created'] = array(
        '#type' => 'item',
        '#title' => t('Created'),
        '#markup' => format_date($job->created),
        '#prefix' => '<div class="tmgmt-ui-created tmgmt-ui-info-item">',
        '#suffix' => '</div>',
        '#value' => $job->created,
      );
    }

    if ($view =  entity_load('view', 'tmgmt_ui_job_items')) {
      $form['job_items_wrapper'] = array(
        '#type' => 'fieldset',
        '#title' => t('Job items'),
        '#collapsible' => TRUE,
        '#weight' => 10,
        '#prefix' => '<div class="tmgmt-ui-job-checkout-fieldset">',
        '#suffix' => '</div>',
      );

      // Translation jobs.
      /* @var $view View */
      $form['job_items_wrapper']['items'] = array(
        '#type' => 'markup',
        '#title' => $view->label(),
        '#prefix' => '<div class="' . 'tmgmt-ui-job-items ' . ($job->isSubmittable() ? 'tmgmt-ui-job-submit' : 'tmgmt-ui-job-manage') . '">',
        'view' => $view->getExecutable()->preview($job->isSubmittable() ? 'submit' : 'block', array($job->tjid)),
        '#attributes' => array('class' => array('tmgmt-ui-job-items', $job->isSubmittable() ? 'tmgmt-ui-job-submit' : 'tmgmt-ui-job-manage')),
        '#suffix' => '</div>',
      );
    }

    // A Wrapper for a button and a table with all suggestions.
    $form['job_items_wrapper']['suggestions'] = array(
      '#type' => 'container',
      '#access' => $job->isSubmittable(),
    );

    // Button to load all translation suggestions with AJAX.
    $form['job_items_wrapper']['suggestions']['load'] = array(
      '#type' => 'submit',
      '#value' => t('Load suggestions'),
      '#submit' => array(array($this, 'loadSuggestionsSubmit')),
      '#limit_validation_errors' => array(),
      '#attributes' => array(
        'class' => array('tmgmt-ui-job-suggestions-load'),
      ),
      '#ajax' => array(
        'callback' => array($this, 'ajaxLoadSuggestions'),
        'wrapper' => 'tmgmt-ui-job-items-suggestions',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );

    $form['job_items_wrapper']['suggestions']['container'] = array(
      '#type' => 'container',
      '#prefix' => '<div id="tmgmt-ui-job-items-suggestions">',
      '#suffix' => '</div>',
    );

    // Create the suggestions table.
    $suggestions_table = array(
      '#type' => 'tableselect',
      '#header' => array(),
      '#options' => array(),
      '#multiple' => TRUE,
    );

    // If this is an AJAX-Request, load all related nodes and fill the table.
    if ($form_state['rebuild'] && !empty($form_state['rebuild_suggestions'])) {
      $this->buildSuggestions($suggestions_table, $form_state);

      // A save button on bottom of the table is needed.
      $suggestions_table = array(
        'suggestions_table' => $suggestions_table,
        'suggestions_add' => array(
          '#type' => 'submit',
          '#value' => t('Add suggestions'),
          '#submit' => array(array($this, 'addSuggestionsSubmit')),
          '#limit_validation_errors' => array(array('suggestions_table')),
          '#attributes' => array(
            'class' => array('tmgmt-ui-job-suggestions-add'),
          ),
          '#access' => !empty($suggestions_table['#options']),
        ),
      );
      $form['job_items_wrapper']['suggestions']['container']['suggestions_list'] = array(
        '#type' => 'fieldset',
        '#title' => t('Suggestions'),
        '#prefix' => '<div id="tmgmt-ui-job-items-suggestions">',
        '#suffix' => '</div>',
      ) + $suggestions_table;
    }

    // Display the checkout settings form if the job can be checked out.
    if ($job->isSubmittable()) {

      $form['translator_wrapper'] = array(
        '#type' => 'fieldset',
        '#title' => t('Configure translator'),
        '#collapsible' => FALSE,
        '#weight' => 20,
      );

      // Show a list of translators tagged by availability for the selected source
      // and target language combination.
      if (!$translators = tmgmt_translator_labels_flagged($job)) {
        drupal_set_message(t('There are no translators available. Before you can checkout you need to !configure at least one translator.', array('!configure' => l(t('configure'), 'admin/config/regional/tmgmt_translator'))), 'warning');
      }
      $preselected_translator = !empty($job->translator) && isset($translators[$job->translator]) ? $job->translator : key($translators);
      $job->translator = isset($form_state['values']['translator']) ? $form_state['values']['translator'] : $preselected_translator;

      $form['translator_wrapper']['translator'] = array(
        '#type' => 'select',
        '#title' => t('Translator'),
        '#description' => t('The configured translator plugin that will process of the translation.'),
        '#options' => $translators,
        '#default_value' => $job->translator,
        '#required' => TRUE,
        '#ajax' => array(
          'callback' => array($this, 'ajaxTranslatorSelect'),
          'wrapper' => 'tmgmt-ui-translator-settings',
        ),
      );

      $settings = $this->checkoutSettingsForm($form_state, $job);
      if(!is_array($settings)){
        $settings = array();
      }
      $form['translator_wrapper']['settings'] = array(
          '#type' => 'fieldset',
          '#title' => t('Checkout settings'),
          '#prefix' => '<div id="tmgmt-ui-translator-settings">',
          '#suffix' => '</div>',
          '#tree' => TRUE,
        ) + $settings;
    }
    // Otherwise display the checkout info.
    elseif (!empty($job->translator)) {

      $form['translator_wrapper'] = array(
        '#type' => 'fieldset',
        '#title' => t('Translator information'),
        '#collapsible' => TRUE,
        '#weight' => 20,
      );

      $form['translator_wrapper']['info'] = $this->checkoutInfo($job);
    }

    if (!$job->isSubmittable() && empty($form['translator_wrapper']['info'])) {
      $form['translator_wrapper']['info'] = array(
        '#type' => 'markup',
        '#markup' => t('The translator does not provide any information.'),
      );
    }

    $form['clearfix'] = array(
      '#markup' => '<div class="clearfix"></div>',
      '#weight' => 45,
    );

    if ($view =  entity_load('view', 'tmgmt_ui_job_messages')) {
      $form['messages'] = array(
        '#type' => 'fieldset',
        '#title' => $view->label(),
        '#collapsible' => TRUE,
        '#weight' => 50,
      );
      $form['messages']['view'] = $view->getExecutable()->preview('block', array($job->tjid));
    }

    $form['#attached']['css'][] = drupal_get_path('module', 'tmgmt_ui') . '/css/tmgmt_ui.admin.css';
    return $form;
  }

  protected function actions(array $form, array &$form_state) {
    $job = $this->entity;

    $actions['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save job'),
      '#validate' => array(
        array($this, 'validate'),
      ),
      '#submit' => array(
        array($this, 'submit'),
        array($this, 'save'),
      ),
    );
    if ($job->access('submit')) {
      $actions['checkout'] = array(
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => tmgmt_ui_redirect_queue_count() == 0 ? t('Submit to translator') : t('Submit to translator and continue'),
        '#access' => $job->isSubmittable(),
        '#disabled' => empty($job->translator),
        '#validate' => array(
          array($this, 'validate'),
        ),
        '#submit' => array(
          array($this, 'submit'),
          array($this, 'save'),
        ),
      );
    }
    if (!$job->isNew()) {
      $actions['delete'] = array(
        '#type' => 'submit',
        '#value' => t('Delete'),
        '#submit' => array('tmgmt_ui_submit_redirect'),
        '#redirect' => 'admin/tmgmt/jobs/' . $job->id() . '/delete',
        // Don't run validations, so the user can always delete the job.
        '#limit_validation_errors' => array(),
      );
    }
    // Only show the 'Cancel' button if the job has been submitted to the
    // translator.
    $actions['cancel'] = array(
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#submit' => array('tmgmt_ui_submit_redirect'),
      '#redirect' => 'admin/tmgmt/jobs',
      '#access' => $job->isActive(),
    );
    return $actions;
  }


  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    parent::validate($form, $form_state);
    $job = $this->buildEntity($form, $form_state);
    // Load the selected translator.
    $translator = tmgmt_translator_load($job->translator);
    // Check translator availability.
    if (!$translator->isAvailable()) {
      form_set_error('translator', $translator->getNotAvailableReason());
    }
    elseif (!$translator->canTranslate($job)) {
      form_set_error('translator', $translator->getNotCanTranslateReason($job));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, array &$form_state) {
    $job = parent::buildEntity($form, $form_state);
    // If requested custom job settings handling, copy values from original job.
    if (tmgmt_job_settings_custom_handling($job->getTranslator())) {
      $original_job = entity_load_unchanged('tmgmt_job', $job->tjid);
      $job->settings = $original_job->settings;
    }

    // Make sure that we always store a label as it can be a slow operation to
    // generate the default label.
    if (empty($job->label)) {
      $job->label = $job->label();
    }
    return $job;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   */
  public function save(array $form, array &$form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    // Per default we want to redirect the user to the overview.
    $form_state['redirect'] = 'admin/tmgmt';
    // Everything below this line is only invoked if the 'Submit to translator'
    // button was clicked.
    if ($form_state['triggering_element']['#value'] == $form['actions']['checkout']['#value']) {
      if (!tmgmt_ui_job_request_translation($entity)) {
        // Don't redirect the user if the translation request failed but retain
        // existing destination parameters so we can redirect once the request
        // finished successfully.
        // @todo: Change this to stay on the form in case of an error instead of
        // doing a redirect.
        $form_state['redirect'] = array(current_path(), array('query' => drupal_get_destination()));
        unset($_GET['destination']);
      }
      else if ($redirect = tmgmt_ui_redirect_queue_dequeue()) {
        // Proceed to the next redirect queue item, if there is one.
        $form_state['redirect'] = $redirect;
      }
      else {
        // Proceed to the defined destination if there is one.
        $form_state['redirect'] = tmgmt_ui_redirect_queue_destination($form_state['redirect']);
      }
    }
  }

  /**
   * Helper function for retrieving the job settings form.
   *
   * @todo Make use of the response object here.
   */
  function checkoutSettingsForm(&$form_state, Job $job) {
    $form = array();
    $translator = $job->getTranslator();
    if (!$translator) {
      return $form;
    }
    if (!$translator->isAvailable()) {
      $form['#description'] = filter_xss($job->getTranslator()->getNotAvailableReason());
    }
    // @todo: if the target language is not defined, the check will not work if the first language in the list is not available.
    elseif ($job->target_language && !$translator->canTranslate($job)) {
      $form['#description'] = filter_xss($job->getTranslator()->getNotCanTranslateReason($job));
    }
    else {
      $plugin_ui = $this->translatorManager->createUIInstance($translator->plugin);
      $form = $plugin_ui->checkoutSettingsForm($form, $form_state, $job);
    }
    return $form;
  }

  /**
   * Helper function for retrieving the rendered job checkout information.
   */
  function checkoutInfo(Job $job) {
    $translator = $job->getTranslator();
    // The translator might have been disabled or removed.
    if (!$translator) {
      return array();
    }
    $plugin_ui = $this->translatorManager->createUIInstance($translator->plugin);
    return $plugin_ui->checkoutInfo($job);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, array &$form_state) {
    $entity = $this->entity;
    $form_state['redirect'] = 'admin/tmgmt/jobs/' . $entity->id() . '/delete';
  }

  /**
   * Ajax callback to fetch the supported translator services and rebuild the
   * target / source language dropdowns.
   */
  public function ajaxLanguageSelect(array $form, array &$form_state) {
    $replace = $form_state['input']['_triggering_element_name'] == 'source_language' ? 'target_language' : 'source_language';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#tmgmt-ui-translator-wrapper', drupal_render($form['translator_wrapper'])));
    $response->addCommand(new ReplaceCommand('#tmgmt-ui-' . str_replace('_', '-', $replace), drupal_render($form['info'][$replace])));

    // Replace value of the label field with ajax on language change.
    // @todo This manual overwrite is necessary because somehow an old job entity seems to be used.
    $form['info']['label']['#value'] = $form_state['values']['label'];
    $response->addCommand(new ReplaceCommand('#tmgmt-ui-label', drupal_render($form['info']['label'])));
    return $response;
  }

  /**
   * Ajax callback to fetch the options provided by a translator.
   */
  public function ajaxTranslatorSelect(array $form, array &$form_state) {
    return $form['translator_wrapper']['settings'];
  }

  /**
   * Adds selected suggestions to the job.
   */
  function addSuggestionsSubmit($form, &$form_state) {
    // Save all selected suggestion items.
    if (isset($form_state['values']['suggestions_table']) && is_array($form_state['values']['suggestions_table'])) {
      $job = $form_state['controller']->getEntity();
      foreach ($form_state['values']['suggestions_table'] as $id) {
        $key = (int)$id - 1; // Because in the tableselect we need an idx > 0.
        if (isset($form_state['tmgmt_suggestions'][$key]['job_item'])) {
          $item = $form_state['tmgmt_suggestions'][$key]['job_item'];
          $job->addExistingItem($item);
        }
      }
    }

    // Force a rebuild of the form.
    $form_state['rebuild'] = TRUE;
    unset($form_state['tmgmt_suggestions']);
  }

  /**
   * Fills the tableselect with all translation suggestions.
   *
   * Calls hook_tmgmt_source_suggestions(Job) and creates the resulting list
   * based on the results from all modules.
   *
   * @param array $suggestions_table
   *   Tableselect part for a $form array where the #options should be inserted.
   * @param array $form_state
   *   The main form_state.
   */
  function buildSuggestions(array &$suggestions_table, array &$form_state) {
    $options = array();
    $job = $form_state['controller']->getEntity();
    if ($job instanceof Job) {
      // Get all suggestions from all modules which implements
      // 'hook_tmgmt_source_suggestions' and cache them in $form_state.
      if (!isset($form_state['tmgmt_suggestions'])) {
        $form_state['tmgmt_suggestions'] = $job->getSuggestions();
      }

      // Remove suggestions which are already processed, translated, ...
      $job->cleanSuggestionsList($form_state['tmgmt_suggestions']);

      // Process all valid entries.
      foreach ($form_state['tmgmt_suggestions'] as $k => $result) {
        if (is_array($result) && isset($result['job_item']) && ($result['job_item'] instanceof JobItem)) {
          $options[$k + 1] = $this->addSuggestionItem($result);
        }
      }

      $suggestions_table['#options'] = $options;
      $suggestions_table['#empty'] = t('No related suggestions available.');
      $suggestions_table['#header'] = array(
        'title' => t('Label'),
        'type' => t('Type'),
        'reason' => t('Reason'),
        'words' => t('Word count'),
      );
    }
  }

  /**
   * Create a Suggestion-Table entry based on a Job and a title.
   *
   * @param array $result
   *   Suggestion array with the keys job_item, reason and from_item.
   *
   * @return array
   *   Options-Entry for a tableselect array.
   */
  function addSuggestionItem(array $result) {
    $item = $result['job_item'];

    $reason = isset($result['reason']) ? $result['reason'] : NULL;
    $option = array(
      'title' => $item->label(),
      'type' => $item->getSourceType(),
      'words' => $item->getWordCount(),
      'reason' => $reason,
    );

    if (!empty($result['from_item'])) {
      $from_item = tmgmt_job_item_load($result['from_item']);
      if ($from_item) {
        $option['reason'] = t('%reason in %job', array('%reason' => $option['reason'], '%job' => $from_item->label()));
      }
    }
    return $option;
  }

  /**
   * Returns the suggestions table for an AJAX-Call.
   */
  function ajaxLoadSuggestions($form, &$form_state) {
    return $form['job_items_wrapper']['suggestions']['suggestions_list'];
  }

  /**
   * Set a value in form_state to rebuild the form and fill with data.
   */
  function loadSuggestionsSubmit(array $form, array &$form_state) {
    $form_state['rebuild'] = TRUE;
    $form_state['rebuild_suggestions'] = TRUE;
  }

}
