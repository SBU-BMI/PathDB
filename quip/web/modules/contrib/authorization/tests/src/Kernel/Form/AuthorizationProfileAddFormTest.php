<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\authorization\Form\AuthorizationProfileAddForm;

/**
 * Test Authorization Profile Add Form.
 *
 * @group authorization
 */
class AuthorizationProfileAddFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'authorization_test',
  ];

  /**
   * Test the profile add form.
   */
  public function testForm() {
    $profile = new AuthorizationProfile([
      'enforceIsNew' => TRUE,
      'id' => 'test_profile',
      'label' => 'Test profile',
      'status' => TRUE,
      'provider' => 'dummy',
      'consumer' => 'dummy',
      'synchronization_modes' => [],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $form = AuthorizationProfileAddForm::create(\Drupal::getContainer());
    $form->setEntity($profile);
    $form->setModuleHandler(\Drupal::moduleHandler());
    $form_state = new FormState();
    $form_array = [];
    $this->assertEquals('authorization_profile_add_form', $form->getFormId());

    $built_form = $form->form($form_array, $form_state);
    $this->assertIsArray($built_form);
    $this->assertCount(7, $built_form);
    $this->assertArrayHasKey('label', $built_form);
    $this->assertArrayHasKey('id', $built_form);
    $this->assertArrayHasKey('status', $built_form);
    $this->assertArrayHasKey('provider', $built_form);
    $this->assertArrayHasKey('consumer', $built_form);
    $this->assertArrayHasKey('#process', $built_form);
    $this->assertArrayHasKey('#after_build', $built_form);

    $form_state->setValue('label', 'Test profile');
    $form_state->setValue('id', 'test_profile');
    $form_state->setValue('status', TRUE);
    $form_state->setValue('provider', 'dummy');
    $form_state->setValue('consumer', 'dummy');

    $form->save($built_form, $form_state);

    $profile = AuthorizationProfile::load('test_profile');
    $this->assertNotNull($profile);
    $this->assertEquals('Test profile', $profile->label());
    $this->assertEquals('test_profile', $profile->id());
    $this->assertTrue($profile->status());
  }

}
