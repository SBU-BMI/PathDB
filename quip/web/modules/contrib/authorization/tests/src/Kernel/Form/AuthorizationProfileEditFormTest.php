<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\authorization\Form\AuthorizationProfileEditForm;

/**
 * Test Authorization Profile Add Form.
 *
 * @group authorization
 */
class AuthorizationProfileEditFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'authorization_test',
  ];

  /**
   * Test the profile edit form.
   */
  public function testForm() {
    $profile = new AuthorizationProfile([
      'id' => 'test_profile',
      'label' => 'Test profile',
      'status' => TRUE,
      'provider' => 'dummy',
      'provider_config' => [],
      'consumer' => 'dummy',
      'consumer_config' => [],
      'provider_mappings' => [[]],
      'consumer_mappings' => [[]],
      'synchronization_modes' => [],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $profile->save();
    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $form->setEntity($profile);
    $form->setModuleHandler(\Drupal::moduleHandler());
    $form_state = new FormState();
    $form_state->setValue('provider', 'dummy');
    $form_state->setValue('consumer', 'dummy');
    $form_state->set('build_dummy_form', TRUE);
    $form_array = [];
    $this->assertEquals('authorization_profile_edit_form', $form->getFormId());

    $built_form = $form->form($form_array, $form_state);
    $this->assertIsArray($built_form);
    $this->assertCount(15, $built_form);
    $this->assertArrayHasKey('#after_build', $built_form);
    $this->assertArrayHasKey('#attached', $built_form);
    $this->assertArrayHasKey('#prefix', $built_form);
    $this->assertArrayHasKey('#process', $built_form);
    $this->assertArrayHasKey('#suffix', $built_form);
    $this->assertArrayHasKey('conditions', $built_form);
    $this->assertArrayHasKey('configuration', $built_form);
    $this->assertArrayHasKey('consumer_config', $built_form);
    $this->assertArrayHasKey('id', $built_form);
    $this->assertArrayHasKey('label', $built_form);
    $this->assertArrayHasKey('mappings_consumer_help', $built_form);
    $this->assertArrayHasKey('mappings_provider_help', $built_form);
    $this->assertArrayHasKey('mappings', $built_form);
    $this->assertArrayHasKey('provider_config', $built_form);
    $this->assertArrayHasKey('status', $built_form);

    $form_state->setValue('label', 'Test profile');
    $form_state->setValue('id', 'test_profile');
    $form_state->setValue('status', TRUE);

    $form->save($built_form, $form_state);

    $profile = AuthorizationProfile::load('test_profile');
    $this->assertNotNull($profile);
    $this->assertEquals('Test profile', $profile->label());
    $this->assertEquals('test_profile', $profile->id());
    $this->assertTrue($profile->status());
  }

  /**
   * Test the profile edit form, without plugins.
   */
  public function testInvalidPlugins() {
    $profile = new AuthorizationProfile([
      'id' => 'test_profile',
      'label' => 'Test profile',
      'status' => TRUE,
      'provider' => 'invalid_provider',
      'provider_config' => [],
      'consumer' => 'invalid_provider',
      'consumer_config' => [],
      'provider_mappings' => [[
        'role' => 'test_role',
      ],
      ],
      'consumer_mappings' => [[
        'role' => 'test_role',
      ],
      ],
      'synchronization_modes' => [],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $form->setEntity($profile);

    $form_state = new FormState();
    $form_array = [];

    $built_form = $form->form($form_array, $form_state);
    $this->assertIsArray($built_form);
    $this->assertCount(15, $built_form);

  }

  /**
   * Test the buildAjaxProviderRowForm.
   */
  public function testBuildAjaxProviderRowForm() {

    $form_state = new FormState();
    $form = [
      'provider_mappings' => [
        [
          'role' => 'test_role',
        ],
      ],
    ];

    $mapping = AuthorizationProfileEditForm::buildAjaxProviderRowForm($form, $form_state);
    $this->assertIsArray($mapping);
    $this->assertCount(1, $mapping);
    $this->assertArrayHasKey('role', $mapping[0]);
    $this->assertEquals('test_role', $mapping[0]['role']);
  }

  /**
   * Test the buildAjaxConsumerRowForm.
   */
  public function testBuildAjaxConsumerRowForm() {

    $form_state = new FormState();
    $form = [
      'consumer_mappings' => [
        [
          'role' => 'test_role',
        ],
      ],
    ];

    $mapping = AuthorizationProfileEditForm::buildAjaxConsumerRowForm($form, $form_state);
    $this->assertIsArray($mapping);
    $this->assertCount(1, $mapping);
    $this->assertArrayHasKey('role', $mapping[0]);
    $this->assertEquals('test_role', $mapping[0]['role']);
  }

  /**
   * Test the mappingsAddAnother.
   */
  public function testMappingsAddAnother() {
    $form_state = new FormState();
    $form_array = [];

    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $form->mappingsAddAnother($form_array, $form_state);
    $this->assertEquals(1, $form_state->get('mappings_fields'));
  }

  /**
   * Test the mappingsAjaxCallback.
   */
  public function testMappingsAjaxCallback() {
    $form_state = new FormState();
    $form_array = [
      'mappings' => [
        [
          'provider_mappings' => [
            'role' => 'test_role',
          ],
          'consumer_mappings' => [
            'role' => 'test_role',
          ],
          'delete' => 0,
        ],
      ],
    ];

    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $result = $form->mappingsAjaxCallback($form_array, $form_state);

    $this->assertIsArray($result);
    $this->assertEquals($form_array['mappings'], $result);
  }

  /**
   * Test the validateForm.
   */
  public function testValidateForm() {
    $profile = new AuthorizationProfile([
      'id' => 'test_profile',
      'label' => 'Test profile',
      'status' => TRUE,
      'provider' => 'dummy',
      'provider_config' => [],
      'consumer' => 'dummy',
      'consumer_config' => [],
      'mappings' => [
        [
          'provider_mappings' => [
            'role' => 'test_role',
          ],
          'consumer_mappings' => [
            'role' => 'test_role',
          ],
          'delete' => 0,
        ],
      ],
      'synchronization_modes' => [],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $profile->save();
    $form_state = new FormState();
    $mappings = [];
    $mappings[0]['provider_mappings']['test'] = 'test';
    $mappings[0]['consumer_mappings']['test'] = 'test';
    $mappings[0]['delete'] = 0;
    $mappings[0]['provider_mappings']['test'] = 'delete';
    $mappings[0]['consumer_mappings']['test'] = 'delete';
    $mappings[0]['delete'] = 1;
    $form_state->setValue('mappings', $mappings);

    $form_state->setUserInput($form_state->getValues());
    $form_array = [
      'provider_config' => [],
      'consumer_config' => [],
      'mappings' => $mappings,
    ];
    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $form->setEntity($profile);
    $form->validateForm($form_array, $form_state);

    $this->assertIsArray($form_state->getErrors());
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Test the submitForm.
   */
  public function testSubmitForm() {
    $profile = new AuthorizationProfile([
      'id' => 'test_profile',
      'label' => 'Test profile',
      'status' => TRUE,
      'provider' => 'dummy',
      'provider_config' => [],
      'consumer' => 'dummy',
      'consumer_config' => [],
      'provider_mappings' => [
        [
          'query' => 'test',
        ],
        [
          'query' => 'delete',
        ],
      ],
      'consumer_mappings' => [
        [
          'role' => 'test',
        ],
        [
          'role' => 'delete',
        ],
      ],
      'synchronization_modes' => [],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $profile->save();
    $form_state = new FormState();
    $mappings = [];
    $mappings[0]['provider_mappings']['query'] = 'test';
    $mappings[0]['consumer_mappings']['role'] = 'test';
    $mappings[0]['delete'] = 0;
    $mappings[1]['provider_mappings']['query'] = 'delete';
    $mappings[1]['consumer_mappings']['role'] = 'delete';
    $mappings[1]['delete'] = 1;
    $form_state->setValue('mappings', $mappings);

    $form_state->setUserInput($form_state->getValues());
    $form_array = [
      'provider_config' => [],
      'consumer_config' => [],
      'mappings' => $mappings,
    ];
    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $form->setEntity($profile);
    $profile = $form->submitForm($form_array, $form_state);

    $this->assertNotNull($profile);
    $this->assertEquals('Test profile', $profile->label());
    $this->assertEquals('test_profile', $profile->id());
    $this->assertTrue($profile->status());
    $this->assertCount(1, $profile->getProviderMappings());
    $this->assertCount(1, $profile->getConsumerMappings());
    $this->assertEquals('dummy', $profile->getProviderId());
    $this->assertEquals('dummy', $profile->getConsumerId());
  }

}
