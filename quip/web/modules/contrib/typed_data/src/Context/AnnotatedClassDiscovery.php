<?php

namespace Drupal\typed_data\Context;

use Drupal\Component\Annotation\Doctrine\SimpleAnnotationReader;
use Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery as CoreAnnotatedClassDiscovery;

@trigger_error('\Drupal\typed_data\Context\AnnotatedClassDiscovery is deprecated in typed_data:8.x-1.0 and is removed from typed_data:2.0.0. Use \Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery instead. See https://www.drupal.org/project/typed_data/issues/3169307', E_USER_DEPRECATED);

/**
 * Extends the annotation class discovery for usage with Typed Data context.
 *
 * We modify the annotations classes for ContextDefinition and for Condition.
 * This class makes sure that our plugin managers apply these.
 *
 * @deprecated in typed_data:8.x-1.0 and is removed from typed_data:2.0.0. Use \Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery instead.
 *
 * @see https://www.drupal.org/project/typed_data/issues/3169307
 */
class AnnotatedClassDiscovery extends CoreAnnotatedClassDiscovery {

  /**
   * {@inheritdoc}
   */
  protected function getAnnotationReader() {
    if (!isset($this->annotationReader)) {
      // Do not call out the parent, but re-configure the simple annotation
      // reader on our own, so we can control the order of namespaces.
      $this->annotationReader = new SimpleAnnotationReader();

      // Make sure to add our namespace first, so our ContextDefinition and
      // Condition annotations gets picked.
      $this->annotationReader->addNamespace('Drupal\typed_data\Context\Annotation');
      // Add the namespaces from the main plugin annotation, like @EntityType.
      $namespace = mb_substr($this->pluginDefinitionAnnotationName, 0, mb_strrpos($this->pluginDefinitionAnnotationName, '\\'));
      $this->annotationReader->addNamespace($namespace);
      // Add general core annotations like @Translation.
      $this->annotationReader->addNamespace('Drupal\Core\Annotation');
    }
    return $this->annotationReader;
  }

}
