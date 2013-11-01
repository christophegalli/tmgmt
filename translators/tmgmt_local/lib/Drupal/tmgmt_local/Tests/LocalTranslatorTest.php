<?php

/**
 * @file
 * Contains \Drupal\tmgmt_local\Tests\LocalTranslatorTest.
 */

namespace Drupal\tmgmt_local\Tests;

use Drupal\tmgmt\Tests\TMGMTTestBase;

/**
 * Basic tests for the local translator.
 */
class LocalTranslatorTest extends TMGMTTestBase {

  /**
   * Translator user.
   *
   * @var object
   */
  protected $local_translator;

  protected $local_translator_permissions = array(
    'provide translation services',
    //'use Rules component rules_tmgmt_local_task_assign_to_me',
    //'use Rules component rules_tmgmt_local_task_unassign',
  );


  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tmgmt_language_combination', 'tmgmt_local', 'tmgmt_ui');

  static function getInfo() {
    return array(
      'name' => 'Local Translator tests',
      'description' => 'Tests the local translator plugin integration.',
      'group' => 'Translation Management',
    );
  }

  function setUp() {
    parent::setUp();
    $this->loginAsAdmin();
    $this->addLanguage('de');

    $this->local_translator = $this->drupalCreateUser($this->local_translator_permissions);

  }

  function testTranslatorSkillsForTasks() {

    $this->addLanguage('fr');

    $translator1 = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($translator1);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator1->id() . '/edit', $edit, t('Save'));

    $translator2 = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($translator2);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'de',
      'tmgmt_translation_skills[1][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));

    $translator3 = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($translator3);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'de',
      'tmgmt_translation_skills[1][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[2][language_from]' => 'en',
      'tmgmt_translation_skills[2][language_to]' => 'fr',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));


    $job1 = $this->createJob('en', 'de');
    $job2 = $this->createJob('de', 'en');
    $job3 = $this->createJob('en', 'fr');

    $local_task1 = tmgmt_local_task_create(array(
      'uid' => $job1->uid,
      'tjid' => $job1->tjid,
      'title' => 'Task 1',
    ));
    $local_task1->save();

    $local_task2 = tmgmt_local_task_create(array(
      'uid' => $job2->uid,
      'tjid' => $job2->tjid,
      'title' => 'Task 2',
    ));
    $local_task2->save();

    $local_task3 = tmgmt_local_task_create(array(
      'uid' => $job3->uid,
      'tjid' => $job3->tjid,
      'title' => 'Task 3',
    ));
    $local_task3->save();

    // Test languages involved in tasks.
    $this->assertEqual(
      tmgmt_local_tasks_languages(array($local_task1->tltid, $local_task2->tltid, $local_task3->tltid)),
      array(
        'en' => array('de', 'fr'),
        'de' => array('en'),
      )
    );

    // Test available translators for task en - de.
    $this->assertEqual(
      tmgmt_local_get_translators_for_tasks(array($local_task1->tltid)),
      array(
        $translator1->id() => $translator1->getUsername(),
        $translator2->id() => $translator2->getUsername(),
        $translator3->id() => $translator3->getUsername(),
      )
    );

    // Test available translators for tasks en - de, de - en.
    $this->assertEqual(
      tmgmt_local_get_translators_for_tasks(array($local_task1->tltid, $local_task2->tltid)),
      array(
        $translator2->id() => $translator2->getUsername(),
        $translator3->id() => $translator3->getUsername(),
      )
    );

    // Test available translators for tasks en - de, de - en, en - fr.
    $this->assertEqual(
      tmgmt_local_get_translators_for_tasks(array($local_task1->tltid, $local_task2->tltid, $local_task3->tltid)),
      array(
        $translator3->id() => $translator3->getUsername(),
      )
    );
  }

  /**
   * Test the basic translation workflow
   */
  function testBasicWorkflow() {
    $translator = tmgmt_translator_load('local');

    // Create a job and request a local translation.
    $this->loginAsTranslator();
    $job = $this->createJob();
    $job->translator = $translator->name;
    $job->settings['job_comment'] = $job_comment = 'Dummy job comment';
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    // Create another local translator with the required capabilities.
    $other_translator_same = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($other_translator_same);
    // Configure language capabilities.
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $other_translator_same->id() . '/edit', $edit, t('Save'));

    // Check that the user is not listed in the translator selection form.
    $uri = $job->uri();
    $this->loginAsAdmin();
    $this->drupalGet($uri['path']);
    $this->assertText(t('Select translator for this job'));
    $this->assertText($other_translator_same->getUsername());
    $this->assertNoText($this->local_translator->getUsername());

    $this->drupalLogin($this->local_translator);
    // Configure language capabilities.
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $this->local_translator->id() . '/edit', $edit, t('Save'));

    // Check that the translator is now listed.
    $this->loginAsAdmin();
    $this->drupalGet($uri['path']);
    $this->assertText($this->local_translator->getUsername());

    $job->requestTranslation();

    // Test for job comment in the job checkout info pane.
    $uri = $job->uri();
    $this->drupalGet($uri['path']);
    $this->assertText($job_comment);

    $this->drupalLogin($this->local_translator);

    // Create a second local translator with different language capabilities,
    // make sure that he does not see the task.
    $other_translator = $this->drupalCreateUser($this->local_translator_permissions);
    $this->drupalLogin($other_translator);
    // Configure language capabilities.
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'de',
      'tmgmt_translation_skills[0][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $other_translator->id() . '/edit', $edit, t('Save'));
    $this->drupalGet('translate');
    $this->assertNoText(t('Task for @job', array('@job' => $job->label())));

    $this->drupalLogin($this->local_translator);

    // Check the translate overview.
    $this->drupalGet('translate');
    $this->assertText(t('Task for @job', array('@job' => $job->label())));
    // @todo: Fails, encoding problem?
    //$this->assertText(t('@from => @to', array('@from' => 'en', '@to' => 'de')));
    $this->clickLink(t('assign'));

    // Log in with the translator with the same capabilities, make sure that he
    // does not see the assigned task.
    $this->drupalLogin($other_translator_same);
    $this->drupalGet('translate');
    $this->assertNoText(t('Task for @job', array('@job' => $job->label())));

    $this->drupalLogin($this->local_translator);

    // @todo: Assign bulk action missing, permission problem?
    /*$edit = array(
      'views_bulk_operations[0]' => $job->tjid,
    );
    $this->drupalPostForm(NULL, $edit, t('Assign to me'));*/


    // Translate the task.
    $this->drupalGet('translate');
    $this->clickLink(t('view'));

    // Assert created local task and task items.
    $this->assertTrue(preg_match('|translate/(\d+)|', $this->getUrl(), $matches), 'Task found');
    $task = tmgmt_local_task_load($matches[1]);
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 0);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 2);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isPending());
    $this->assertEqual($first_task_item->getCountCompleted(), 0);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 1);

    $this->assertText('test_source:test:1');
    $this->assertText('test_source:test:2');
    $this->assertText(t('Untranslated'));

    // Translate the first item.
    $this->clickLink(t('translate'));

    $this->assertText(t('Dummy'));
    // Job comment is present in the translate tool as well.
    $this->assertText($job_comment);
    $this->assertText('test_source:test:1');

    // Try to complete a translation when translations are missing.
    $this->drupalPostForm(NULL, array(), t('Save as completed'));
    $this->assertText(t('Missing translation.'));

    $edit = array(
      'dummy|deep_nesting[translation]' => $translation1 = 'German translation of source 1',
    );
    $this->drupalPostForm(NULL, $edit, t('Save as completed'));

    // The first item should be accepted now, the second still in progress.
    $this->assertText(t('Completed'));
    $this->assertText(t('Untranslated'));

    entity_get_controller('tmgmt_local_task')->resetCache();
    entity_get_controller('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $task = tmgmt_local_task_load($task->tltid);
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 1);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 1);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isPending());
    $this->assertEqual($second_task_item->getCountCompleted(), 0);
    $this->assertEqual($second_task_item->getCountTranslated(), 0);
    $this->assertEqual($second_task_item->getCountUntranslated(), 1);

    // Translate the second item but do not mark as translated it yet.
    $this->clickLink(t('translate'));
    $edit = array(
      'dummy|deep_nesting[translation]' => $translation2 = 'German translation of source 2',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // The first item is still completed, the second still untranslated.
    $this->assertText(t('Completed'));
    $this->assertText(t('Untranslated'));

    entity_get_controller('tmgmt_local_task')->resetCache();
    entity_get_controller('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $task = tmgmt_local_task_load($task->tltid);
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 1);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 1);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isPending());
    $this->assertEqual($second_task_item->getCountCompleted(), 0);
    $this->assertEqual($second_task_item->getCountTranslated(), 0);
    $this->assertEqual($second_task_item->getCountUntranslated(), 1);

    // Mark the data item as translated but don't save the task item as
    // completed.
    $this->clickLink(t('translate'));
    $this->drupalPostForm(NULL, array(), t('✓'));

    entity_get_controller('tmgmt_local_task')->resetCache();
    entity_get_controller('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $task = tmgmt_local_task_load($task->tltid);
    $this->assertTrue($task->isPending());
    $this->assertEqual($task->getCountCompleted(), 1);
    $this->assertEqual($task->getCountTranslated(), 1);
    $this->assertEqual($task->getCountUntranslated(), 0);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isPending());
    $this->assertEqual($second_task_item->getCountCompleted(), 0);
    $this->assertEqual($second_task_item->getCountTranslated(), 1);
    $this->assertEqual($second_task_item->getCountUntranslated(), 0);

    // Check the job data.
    entity_get_controller('tmgmt_job')->resetCache(array($job->tjid));
    entity_get_controller('tmgmt_job_item')->resetCache();
    $job = tmgmt_job_load($job->tjid);
    list($item1, $item2) = array_values($job->getItems());
    // The text in the first item should be available for review, the
    // translation of the second item not.
    $this->assertEqual($item1->getData(array('dummy', 'deep_nesting', '#translation', '#text')), $translation1);
    $this->assertEqual($item2->getData(array('dummy', 'deep_nesting', '#translation', '#text')), '');

    // Check the overview page, the task should still show in progress.
    $this->drupalGet('translate');
    $this->assertText(t('Pending'));

    // Mark the second item as completed now.
    $this->clickLink(t('view'));
    $this->clickLink(t('translate'));
    $this->drupalPostForm(NULL, array(), t('Save as completed'));

    entity_get_controller('tmgmt_local_task')->resetCache();
    entity_get_controller('tmgmt_local_task_item')->resetCache();
    drupal_static_reset('tmgmt_local_task_statistics_load');
    $task = tmgmt_local_task_load($task->tltid);
    $this->assertTrue($task->isClosed());
    $this->assertEqual($task->getCountCompleted(), 2);
    $this->assertEqual($task->getCountTranslated(), 0);
    $this->assertEqual($task->getCountUntranslated(), 0);
    list($first_task_item, $second_task_item) = array_values($task->getItems());
    $this->assertTrue($first_task_item->isClosed());
    $this->assertEqual($first_task_item->getCountCompleted(), 1);
    $this->assertEqual($first_task_item->getCountTranslated(), 0);
    $this->assertEqual($first_task_item->getCountUntranslated(), 0);
    $this->assertTrue($second_task_item->isClosed());
    $this->assertEqual($second_task_item->getCountCompleted(), 1);
    $this->assertEqual($second_task_item->getCountTranslated(), 0);
    $this->assertEqual($second_task_item->getCountUntranslated(), 0);

    // We should have been redirect back to the overview, the task should be
    // completed now.
    $this->assertNoText($task->getJob()->label());
    $this->clickLink(t('Closed'));
    $this->assertText($task->getJob()->label());
    $this->assertText(t('Completed'));

    entity_get_controller('tmgmt_job')->resetCache(array($job->tjid));
    entity_get_controller('tmgmt_job_item')->resetCache();
    $job = tmgmt_job_load($job->tjid);
    list($item1, $item2) = array_values($job->getItems());
    // Job was accepted and finished automatically due to the default approve
    // setting.
    $this->assertTrue($job->isFinished());
    $this->assertEqual($item1->getData(array(
      'dummy',
      'deep_nesting',
      '#translation',
      '#text'
    )), $translation1);
    $this->assertEqual($item2->getData(array(
      'dummy',
      'deep_nesting',
      '#translation',
      '#text'
    )), $translation2);

    // Delete the job, make sure that the corresponding task and task items were
    // deleted.
    $job->delete();
    $this->assertFalse(tmgmt_local_task_item_load($task->tltid));
    $this->assertFalse($task->getItems());
  }

  /**
   * Test the allow all setting.
   */
  function testAllowAll() {
    $translator = tmgmt_translator_load('local');

    // Create a job and request a local translation.
    $this->loginAsTranslator();
    $job = $this->createJob();
    $job->translator = $translator->name;
    $job->addItem('test_source', 'test', '1');
    $job->addItem('test_source', 'test', '2');

    $this->assertFalse($job->requestTranslation(), 'Translation request was denied.');

    // Now enable the setting.
    $translator->settings['allow_all'] = TRUE;
    $translator->save();

    $this->assertIdentical(NULL, $job->requestTranslation(), 'Translation request was successfull');
    $this->assertTrue($job->isActive());
  }

  function testCapabilitiesAPI() {

    $this->addLanguage('fr');
    $this->addLanguage('ru');
    $this->addLanguage('it');

    $all_translators = array();

    $translator1 = $this->drupalCreateUser($this->local_translator_permissions);
    $all_translators[$translator1->id()] = $translator1->getUsername();
    $this->drupalLogin($translator1);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'de',
    );
    $this->drupalPostForm('user/' . $translator1->id() . '/edit', $edit, t('Save'));

    $translator2 = $this->drupalCreateUser($this->local_translator_permissions);
    $all_translators[$translator2->id()] = $translator2->getUsername();
    $this->drupalLogin($translator2);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'en',
      'tmgmt_translation_skills[0][language_to]' => 'ru',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'en',
      'tmgmt_translation_skills[1][language_to]' => 'fr',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[2][language_from]' => 'fr',
      'tmgmt_translation_skills[2][language_to]' => 'it',
    );
    $this->drupalPostForm('user/' . $translator2->id() . '/edit', $edit, t('Save'));

    $translator3 = $this->drupalCreateUser($this->local_translator_permissions);
    $all_translators[$translator3->id()] = $translator3->getUsername();
    $this->drupalLogin($translator3);
    $edit = array(
      'tmgmt_translation_skills[0][language_from]' => 'fr',
      'tmgmt_translation_skills[0][language_to]' => 'ru',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));
    $edit = array(
      'tmgmt_translation_skills[1][language_from]' => 'it',
      'tmgmt_translation_skills[1][language_to]' => 'en',
    );
    $this->drupalPostForm('user/' . $translator3->id() . '/edit', $edit, t('Save'));

    // Test target languages.
    $target_languages = tmgmt_local_supported_target_languages('fr');
    $this->assertTrue(isset($target_languages['it']));
    $this->assertTrue(isset($target_languages['ru']));
    $target_languages = tmgmt_local_supported_target_languages('en');
    $this->assertTrue(isset($target_languages['fr']));
    $this->assertTrue(isset($target_languages['ru']));

    // Test language pairs.
    $this->assertEqual(tmgmt_local_supported_language_pairs(), array (
      'en__de' =>
        array(
          'source_language' => 'en',
          'target_language' => 'de',
        ),
      'en__ru' =>
        array(
          'source_language' => 'en',
          'target_language' => 'ru',
        ),
      'en__fr' =>
        array(
          'source_language' => 'en',
          'target_language' => 'fr',
        ),
      'fr__it' =>
        array(
          'source_language' => 'fr',
          'target_language' => 'it',
        ),
      'fr__ru' =>
        array(
          'source_language' => 'fr',
          'target_language' => 'ru',
        ),
      'it__en' =>
        array(
          'source_language' => 'it',
          'target_language' => 'en',
        ),
    ));
    $this->assertEqual(tmgmt_local_supported_language_pairs('fr', array($translator2->id())), array(
      'fr__it' =>
        array(
          'source_language' => 'fr',
          'target_language' => 'it',
        ),
    ));

    // Test if we got all translators.
    $translators = tmgmt_local_translators();
    foreach ($all_translators as $uid => $name) {
      if (!isset($translators[$uid])) {
        $this->fail('Expected translator not present');
      }
      if (!in_array($name, $all_translators)) {
        $this->fail('Expected translator name not present');
      }
    }

    // Only translator2 has such capabilities.
    $translators = tmgmt_local_translators('en', array('ru', 'fr'));
    $this->assertTrue(isset($translators[$translator2->id()]));
  }
}