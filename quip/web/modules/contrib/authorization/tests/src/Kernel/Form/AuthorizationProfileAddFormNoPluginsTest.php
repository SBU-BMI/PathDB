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
class AuthorizationProfileAddFormNoPluginsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
  ];

  /**
   * Test the profile add form without plugins.
   */
  public function testFormWithoutPlugins() {
    $profile = new AuthorizationProfile([], 'authorization_profile');
    $form = AuthorizationProfileAddForm::create(\Drupal::getContainer());
    $form->setEntity($profile);
    $form->setModuleHandler(\Drupal::moduleHandler());
    $form_state = new FormState();
    $form_array = [];
    $this->assertEquals('authorization_profile_add_form', $form->getFormId());

    $built_form = $form->form($form_array, $form_state);
    $this->assertIsArray($built_form);
    $this->assertCount(8, $built_form);
    $this->assertArrayHasKey('#access', $built_form);
    $this->assertArrayHasKey('#after_build', $built_form);
    $this->assertArrayHasKey('#cache', $built_form);
    $this->assertArrayHasKey('#markup', $built_form);
    $this->assertArrayHasKey('#process', $built_form);
    $this->assertArrayHasKey('id', $built_form);
    $this->assertArrayHasKey('label', $built_form);
    $this->assertArrayHasKey('status', $built_form);

    $this->assertEquals('Authorization profile cannot be created.', (string) $built_form['#markup']);
  }

}
