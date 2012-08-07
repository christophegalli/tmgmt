<?php

/**
 * @file
 * Contains Drupal\tmgmt_test\TestSourcePluginController.
 */

namespace Drupal\tmgmt_test\Plugin\tmgmt\Source;

use Drupal\tmgmt\SourcePluginBase;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Annotation\SourcePlugin;
use Drupal\Core\Annotation\Translation;

/**
 * Test source plugin implementation.
 *
 * @SourcePlugin(
 *   id = "test_source",
 *   label = @Translation("Test source"),
 *   description = @Translation("Simple source for testing purposes.")
 * )
 */
class TestSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getUri(JobItem $job_item) {
    // Provide logic which allows to test for source which is either accessible
    // or not accessible to anonymous user. This is may then be used to test if
    // the source url is attached to the job comment sent to a translation
    // service.
    $path = 'node';
    if ($job_item->item_type == 'test_not_accessible') {
      $path = 'admin';
    }
    return array('path' => $path, 'options' => array());
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(JobItem $job_item) {
    return $this->pluginId . ':' . $job_item->item_type . ':' . $job_item->item_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(JobItem $job_item) {
    return array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => 'Text for job item with type ' . $job_item->item_type . ' and id ' . $job_item->item_id . '.',
          '#label' => 'Label for job item with type ' . $job_item->item_type . ' and id ' . $job_item->item_id . '.',
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function saveTranslation(JobItem $job_item) {
    // Set a variable that can be checked later for a given job item.
    \Drupal::state()->set('tmgmt_test_saved_translation_' . $job_item->item_type . '_' . $job_item->item_id, TRUE);
    $job_item->accepted();
  }
}
