<?php

namespace Drupal\moderated_content_bulk_publish;

class HookObject {

  public $nid = 0;
  public $body_field_val = '';
  public $bundle = '';
  public $show_button = TRUE;
  public $markup = '';
  public $error_message = '';
  public $validate_failure = FALSE;
  public $msgdetail_isToken = '';
  public $msgdetail_isPublished = '';
  public $msgdetail_isAbsoluteURL = '';

}