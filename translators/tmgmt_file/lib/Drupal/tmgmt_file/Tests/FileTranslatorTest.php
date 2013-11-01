<?php

/**
 * @file
 * Contains Drupal\tmgmt_file\Tests\FileTranslatorTest.
 */

namespace Drupal\tmgmt_file\Tests;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Tests\TMGMTTestBase;
use Guzzle\Http\Exception\ClientErrorResponseException;

/**
 * Basic tests for the file translator.
 */
class FileTranslatorTest extends TMGMTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  static public $modules = array('tmgmt_file', 'tmgmt_ui');

  static function getInfo() {
    return array(
      'name' => 'File Translator tests',
      'description' => 'Tests the file translator plugin integration.',
      'group' => 'Translation Management',
    );
  }

  function setUp() {
    parent::setUp();
    $this->loginAsAdmin();
    $this->addLanguage('de');
  }

  /**
   * Tests export and import for the HTML format.
   */
  function testHTML() {
    $translator = tmgmt_translator_load('file');
    $translator->settings = array(
      'export_format' => 'html',
    );
    $translator->save();

    $job = $this->createJob();
    $job->translator = $translator->name;
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    $job->requestTranslation();
    $messages = $job->getMessages();
    $message = reset($messages);

    // @todo: This should be handled by the storage controller.
    $variables = $message->variables;
    $download_url = $variables['!link'];

    // "Translate" items.
    $xml = simplexml_load_file($download_url);
    $translated_text = array();
    foreach ($xml->body->children() as $group) {
      for ($i = 0; $i < $group->count(); $i++) {
        // This does not actually override the whole object, just the content.
        $group->div[$i] = (string) $xml->head->meta[3]['content'] . '_' . (string) $group->div[$i];
        // Store the text to allow assertions later on.
        $translated_text[(string) $group['id']][(string) $group->div[$i]['id']] = (string) $group->div[$i];
      }
    }

    $translated_file = 'public://tmgmt_file/translated.html';
    $xml->asXML($translated_file);
    $this->importFile($translated_file, $translated_text, $job);
  }

  /**
   * Tests import and export for the xliff format.
   */
  function testXLIFF() {
    $translator = tmgmt_translator_load('file');
    $translator->settings = array(
      'export_format' => 'xlf',
    );
    $translator->save();

    $job = $this->createJob();
    $job->translator = $translator->name;
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    $job->requestTranslation();
    $messages = $job->getMessages();
    $message = reset($messages);

    $variables = $message->variables;
    $download_url = $variables['!link'];
    $xliff = file_get_contents($download_url);
    $dom = new \DOMDocument();
    $dom->loadXML($xliff);
    debug($xliff);
    $this->assertTrue($dom->schemaValidate(drupal_get_path('module', 'tmgmt_file') . '/xliff-core-1.2-strict.xsd'));

    // "Translate" items.
    $xml = simplexml_import_dom($dom);
    $translated_text = array();
    foreach ($xml->file->body->children() as $group) {
      foreach ($group->children() as $transunit) {
        if ($transunit->getName() == 'trans-unit') {
          $transunit->target = $xml->file['target-language'] . '_' . (string) $transunit->source;
          // Store the text to allow assertions later on.
          $translated_text[(string) $group['id']][(string) $transunit['id']] = (string) $transunit->target;
        }
      }
    }

    // Change the job id to a non-existing one and try to import it.
    $wrong_xml = clone $xml;
    $wrong_xml->file->header->{'phase-group'}->phase['job-id'] = 500;
    $wrong_file = 'public://tmgmt_file/wrong_file.xlf';
    $wrong_xml->asXML($wrong_file);
    $uri = $job->uri();
    $edit = array(
      'files[file]' => $wrong_file,
    );
    $this->drupalPostForm($uri['path'], $edit, t('Import'));
    $this->assertText(t('Failed to validate file, import aborted.'));

    // Change the job id to a wrong one and try to import it.
    $wrong_xml = clone $xml;
    $second_job = $this->createJob();
    $second_job->translator = $translator->name;
    $second_job->save();
    $wrong_xml->file->header->{'phase-group'}->phase['job-id'] = $second_job->tjid;
    $wrong_file = 'public://tmgmt_file/wrong_file.xlf';
    $wrong_xml->asXML($wrong_file);
    $uri = $job->uri();
    $edit = array(
      'files[file]' => $wrong_file,
    );
    $this->drupalPostForm($uri['path'], $edit, t('Import'));
    $uri = $second_job->uri();
    $label = $second_job->label();
    $this->assertRaw(t('Import file is from job <a href="@url">@label</a>, import aborted.', array('@url' => url($uri['path']), '@label' => $label)));


    $translated_file = 'public://tmgmt_file/translated file.xlf';
    $xml->asXML($translated_file);
    $this->importFile($translated_file, $translated_text, $job);

    $this->assertNoText(t('Import translated file'));

    // Create a job, assign to the file translator and delete before attaching
    // a file.
    $other_job = $this->createJob();
    $other_job->translator = $translator->name;
    $other_job->save();
    $other_job->delete();
    // Make sure the file of the other job still exists.
    $response = \Drupal::httpClient()->get($download_url)->send();
    $this->assertEqual(200, $response->getStatusCode());

    // Delete the job and then make sure that the file has been deleted.
    $job->delete();
    try {
      $response = \Drupal::httpClient()->get($download_url)->send();
      $this->fail('Expected exception not thrown.');
    }
    catch (ClientErrorResponseException $e) {
      $this->assertEqual(404, $e->getResponse()->getStatusCode());
    }
  }


  /**
   * Tests storing files in the private file system.
   */
  function testPrivate() {
    // Enable the private file system.
    variable_set('file_private_path', variable_get('file_public_path') . '/private');

    // Create a translator using the private file system.
    // @todo: Test the configuration UI.
    $translator = $this->createTranslator();
    $translator->plugin = 'file';
    $translator->settings = array(
      'export_format' => 'xlf',
      'scheme' => 'private',
    );
    $translator->save();

    $job = $this->createJob();
    $job->translator = $translator->name;
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    $job->requestTranslation();
    $messages = $job->getMessages();
    $message = reset($messages);

    $download_url = $message->variables['!link'];
    $this->drupalGet($download_url);
    // Verify that the URL is served using the private file system and the
    // access checks work.
    $this->assertTrue(preg_match('|system/files|', $download_url));
    $this->assertResponse(200);

    $this->drupalLogout();
    // Verify that access is now protected.
    $this->drupalGet($download_url);
    $this->assertResponse(403);
  }

  protected function importFile($translated_file, $translated_text, Job $job) {
    // To test the upload form functionality, navigate to the edit form.
    $uri = $job->uri();
    $edit = array(
      'files[file]' => $translated_file,
    );
    $this->drupalPostForm($uri['path'], $edit, t('Import'));

    // Make sure the translations have been imported correctly.
    $this->assertNoText(t('In progress'));
    // @todo: Enable this assertion once new releases for views and entity
    // module are out.
    //$this->assertText(t('Needs review'));

    // Review both items.
    $this->clickLink(t('review'));
    foreach ($translated_text[1] as $key => $value) {
      $this->assertText($value);
    }
    foreach ($translated_text[2] as $key => $value) {
      $this->assertNoText($value);
    }
    $this->drupalPostForm(NULL, array(), t('Save as completed'));
    // Review both items.
    $this->clickLink(t('review'));
    foreach ($translated_text[1] as $key => $value) {
      $this->assertNoText($value);
    }
    foreach ($translated_text[2] as $key => $value) {
      $this->assertText($value);
    }
    $this->drupalPostForm(NULL, array(), t('Save as completed'));
    // @todo: Enable this assertion once new releases for views and entity
    // module are out.
    //$this->assertText(t('Accepted'));
    $this->assertText(t('Finished'));
    $this->assertNoText(t('Needs review'));
  }
}
