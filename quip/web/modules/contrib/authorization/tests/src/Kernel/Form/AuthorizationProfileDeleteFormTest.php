<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\authorization\Form\AuthorizationProfileDeleteForm;

/**
 * Test Authorization Profile Delete Form.
 *
 * @group authorization
 */
class AuthorizationProfileDeleteFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'authorization_test',
  ];

  /**
   * Test the delete form.
   */
  public function testForm() {
    $profile = new AuthorizationProfile([
      'id' => 'test',
      'label' => 'Test profile',
      'description' => 'Test profile',
      'status' => 'true',
      'provider' => 'dummy',
      'consumer' => 'dummy',
      'synchronization_modes' => [
        'user_logon' => 'user_logon',
      ],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $profile->save();

    $form = AuthorizationProfileDeleteForm::create(\Drupal::getContainer());
    $form->setEntity($profile);
    $form->setModuleHandler(\Drupal::moduleHandler());
    $form_state = new FormState();
    $form_array = [];
    $this->assertEquals('authorization_profile_delete_form', $form->getFormId());

    $built_form = $form->buildForm([], $form_state);
    $this->assertIsArray($built_form);
    $this->assertCount(9, $built_form);
    $this->assertArrayHasKey('#after_build', $built_form);
    $this->assertArrayHasKey('#attributes', $built_form);
    $this->assertArrayHasKey('#cache', $built_form);
    $this->assertArrayHasKey('#process', $built_form);
    $this->assertArrayHasKey('#theme', $built_form);
    $this->assertArrayHasKey('#title', $built_form);
    $this->assertArrayHasKey('actions', $built_form);
    $this->assertArrayHasKey('confirm', $built_form);
    $this->assertArrayHasKey('description', $built_form);

    $confirm_text = (string) $form->getConfirmText();
    $this->assertEquals('Delete', $confirm_text);

    $cancel_url = $form->getCancelUrl();
    $this->assertEquals('entity.authorization_profile.edit_form', $cancel_url->getRouteName());

    $question = (string) $form->getQuestion();
    $this->assertEquals('Are you sure you want to delete <em class="placeholder">Test profile</em>?', $question);

    $form->submitForm($form_array, $form_state);

    $this->assertEquals('entity.authorization_profile.collection', $form_state->getRedirect()->getRouteName());

    $missing_profile = AuthorizationProfile::load('test');
    $this->assertNull($missing_profile);
  }

}
