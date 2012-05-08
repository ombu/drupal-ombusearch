<?php

/**
 * @file
 * Facetapi callback overrides.
 *
 * Pulls in old values callbacks from facetapi project, for use in selecting
 * default value in solr_bean config form. Once facetapi implements value
 * callbacks again, these can go away.  See http://drupal.org/node/1320324 for
 * more information.
 */

/**
 * Returns values for the language facet.
 *
 * @param $facet
 *   An array containing the facet definition.
 *
 * @return
 *   An array of values passed as options to the form element.
 */
if (!function_exists('facetapi_callback_type_values')) {
  function facetapi_callback_type_values(array $facet) {
    return array_map('check_plain', node_type_get_names());
  }
}

/**
 * Returns all user names.
 *
 * @param $facet
 *   An array containing the facet definition.
 *
 * @return
 *   An array of values passed as options to the form element.
 */
if (!function_exists('facetapi_callback_user_values')) {
  function facetapi_callback_user_values(array $facet) {
    $users = db_query("SELECT uid, name FROM {users} LEFT JOIN {node} USING(uid) WHERE nid IS NOT NULL AND uid != 0")->fetchAllKeyed();
    return $users;
  }
}

/**
 * Returns values for vocabulary facets.
 *
 * @param $facet
 *   An array containing the facet definition.
 *
 * @return
 *   An array of values passed as options to the form element.
 */
if (!function_exists('facetapi_callback_taxonomy_values')) {
  function facetapi_callback_taxonomy_values(array $facet) {
    if (!$vocabulary = taxonomy_vocabulary_machine_name_load($facet['map options']['field']['settings']['allowed_values'][0]['vocabulary'])) {
      return array();
    }

    // Builds options from taxonomy tree.
    $options = array();
    $tree = taxonomy_get_tree($vocabulary->vid);
    if ($tree && (count($tree) > 0)) {
      foreach ($tree as $term) {
        $options[$term->tid] = check_plain(str_repeat('-', $term->depth) . $term->name);
      }
    }
    return $options;
  }
}
