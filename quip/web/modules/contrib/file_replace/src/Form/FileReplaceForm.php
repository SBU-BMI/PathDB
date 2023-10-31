<?php

namespace Drupal\file_replace\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the file replace forms.
 */
class FileReplaceForm extends ContentEntityForm {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->setModuleHandler($container->get('module_handler'));
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->getEntity();

    $form['original'] = [
      '#type' => 'fieldset',
      '#title' => t('Original'),
    ];
    $form['original']['link'] = [
      '#theme' => 'file_link',
      '#file' => $file,
      '#cache' => [
        'tags' => $file->getCacheTags(),
      ],
    ];

    $extension = pathinfo($file->getFileUri(), PATHINFO_EXTENSION);

    $form['replacement'] = [
      '#type' => 'fieldset',
      '#title' => t('Replacement'),
    ];
    $form['replacement']['replacement'] = [
      '#type' => 'file',
      '#description' => $this->t('Select a file with extension .%extension and mimetype %mimetype to replace this file with.',
        [
          '%extension' => $extension,
          '%mimetype' => $file->getMimeType(),
        ]),
      '#upload_validators' => [
        'file_validate_extensions' => [$extension],
      ],
      '#attributes' => [
        'accept' => $file->getMimeType(),
      ],
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entity;
    $file_uri = $file->getFileUri();

    /** @var \Drupal\file\FileInterface $replacement */
    $replacement = file_save_upload('replacement', $form['replacement']['replacement']['#upload_validators'], FALSE, 0);
    if (!$replacement) {
      $this->messenger()->addError(t('The replacement file was not saved'));
      return;
    }

    if (!$this->fileSystem->copy($replacement->getFileUri(), $file_uri, FileSystemInterface::EXISTS_REPLACE)) {
      $this->messenger()->addError(t('The file could not be replaced'));
      return;
    }

    // Recalculate file size and change date.
    $file->save();

    $this->messenger()->addStatus(t('The file was replaced.'));
    $this->moduleHandler->invokeAll('file_replace', [$file]);

    // Clean up the temporary file.
    $replacement->delete();
  }

}
