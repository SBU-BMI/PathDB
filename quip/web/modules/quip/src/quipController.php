<?php
/**
 * @file
 * Contains \Drupal\mymodule\quipController.
 */

namespace Drupal\quip;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

class quipController extends ControllerBase {
  public function content(NodeInterface $node, $modestate) {
	//$node->title->value = 'YAY 192 '.$modestate;
	$node->moderation_state = $modestate;
	$node->save();
    return $this->redirect('view.review.page_1');
  }
}

//              $markup = $markup.$nn;
//    return array(
//        '#markup' => $markup,
//    );
