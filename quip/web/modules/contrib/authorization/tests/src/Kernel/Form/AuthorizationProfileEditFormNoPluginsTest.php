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
class AuthorizationProfileEditFormNoPluginsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
  ];

  /**
   * Test the profile edit form. No plugins installed.
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
      'synchronization_modes' => [],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $form = AuthorizationProfileEditForm::create(\Drupal::getContainer());
    $form->setEntity($profile);

    $form_state = new FormState();
    $form_array = [];

    $built_form = $form->form($form_array, $form_state);
    $this->assertIsArray($built_form);
    $this->assertCount(14, $built_form);
    $this->assertArrayHasKey('#access', $built_form);
    $this->assertArrayHasKey('#after_build', $built_form);
    $this->assertArrayHasKey('#attached', $built_form);
    $this->assertArrayHasKey('#cache', $built_form);
    $this->assertArrayHasKey('#markup', $built_form);
    $this->assertArrayHasKey('#prefix', $built_form);
    $this->assertArrayHasKey('#process', $built_form);
    $this->assertArrayHasKey('#suffix', $built_form);
    $this->assertArrayHasKey('configuration', $built_form);
    $this->assertArrayHasKey('consumer_config', $built_form);
    $this->assertArrayHasKey('id', $built_form);
    $this->assertArrayHasKey('label', $built_form);
    $this->assertArrayHasKey('provider_config', $built_form);
    $this->assertArrayHasKey('status', $built_form);

    $this->assertEquals('Authorization profile cannot be edited.', (string) $built_form['#markup']);
  }

}
