<?php

/**
 * @file
 * Teaser style for solr bean.
 */

class SolrTeaserBeanStyle extends SolrBeanStyle {
  /**
   * View mode for rendering entities.
   *
   * @param string
   */
  public $view_mode = 'teaser';

  /**
   * Implements parent::prepareItems().
   */
  protected function prepareItems($build, $type) {
    foreach ($this->results as $result) {
      $entity_type = $result['entity_type'];
      $entity_id = $result['fields']['entity_id'];

      // Default to teaser if view mode isn't a valid node view.
      $entity_info = entity_get_info($entity_type);

      // Render entity appropriately.
      if ($entity_info) {
        $entity = entity_load($entity_type, array($entity_id));
        if ($entity) {
          $rendered_result = entity_view(
            $entity_type,
            $entity,
            $this->view_mode
          );
          $rendered_result = $rendered_result[$entity_type][$entity_id];
          $rendered_result['#result'] = $result;

          $this->items[] = $rendered_result;
        }
      }
      else {
        // todo: if result is not an entity, then render using default solr
        // result.
      }
    }
  }
}
