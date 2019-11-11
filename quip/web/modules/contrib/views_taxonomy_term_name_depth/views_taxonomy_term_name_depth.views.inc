<?php

/**
 * @file
 * Contains Views Taxonomy Term Name Depth hooks.
 */

/**
 * Implements hook_views_data_alter().
 */
function views_taxonomy_term_name_depth_views_data_alter(&$data) {
  $data['node_field_data']['term_node_taxonomy_name_depth'] = [
    'help' => t('Display content if it has the selected taxonomy terms, or children of the selected terms. Due to additional complexity, this has fewer options than the versions without depth.'),
    'real field' => 'nid',
    'argument' => [
      'title' => t('Has taxonomy term NAME (with depth)'),
      'id' => 'taxonomy_index_name_depth',
      'accept depth modifier' => TRUE,
    ],
  ];
}
