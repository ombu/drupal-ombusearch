<?php

/**
 * @file
 * URL processor to aid in the complex setting of facetapi values for solr
 * beans.
 */

/**
 * Extension of FacetapiUrlProcessor.
 */
class SolrBeanUrlProcessor extends FacetapiUrlProcessorStandard {
  /**
   * The bean object currently being viewed.
   */
  protected $bean;

  /**
   * Sets the bean object for this processor.
   */
  public function setBean($bean) {
    $this->bean = $bean;
  }

  /**
   * Returns the path for a facet item.
   *
   * @param array $facet
   *   The facet definition.
   * @param array $values
   *   An array containing the item's values being added to or removed from the
   *   query string dependent on whether or not the item is active.
   * @param int $active
   *   An integer flagging whether the item is active or not.
   *
   * @return string
   *   The path of the facet.
   */
  public function getFacetPath(array $facet, array $values, $active) {
    if (isset($this->bean)) {
      return current_path();
    }
    else {
      return parent::getFacetPath($facet, $values, $active);
    }
  }

  /**
   * Implements FacetapiUrlProcessor::fetchParams().
   *
   * Pulls facet params from the $_GET variable, or sets them via the solr_bean
   * settings.
   * @todo: namespace GET variables on a per bean basis, so there can be
   *   multiple solr beans with filters on a single page.  E.g.
   *   $_GET['solr_bean_delta']['f'] would hold the facet info for the solr bean
   *   with delta of `solr_bean_delta`
   *
   */
  public function fetchParams() {
    if (isset($this->bean)) {
      $conditions['f'] = isset($_GET['f']) ? $_GET['f'] : array();

      $filter_queries = array();
      if (!empty($_GET['f'])) {
        foreach ($_GET['f'] as $f) {
          list($key, $value) = explode(':', $f);
          $filer_queries[] = $key;
        }
      }

      // Set default values if needed for facets.
      foreach ($this->bean->facets as $key => $data) {
        if ($key == 'keys') {
          continue;
        }

        if (!empty($data['default_value']) && !in_array($data['field'], $filter_queries)) {
          if (is_array($data['default_value'])) {
            // If facet is a date field, handle complex faceting.
            if (isset($data['default_value']['date_range_start_op'])) {
              $conditions['f'][] = $data['field'] . ':' . $this->generateDateRange($data['default_value']);
            }
            else {
              $data['default_value'] = array_filter($data['default_value']);
              foreach ($data['default_value'] as $value) {
                $conditions['f'][] = $data['field'] . ':' . $value;
              }
            }
          }
          else {
            $conditions['f'][] = $data['field'] . ':' . $data['default_value'];
          }
        }
      }
      return $conditions;
    }
    else {
      return parent::fetchParams();
    }
  }

  /**
   * Implements FacetapiUrlProcessor::getQueryString().
   */
  public function getQueryString(array $facet, array $values, $active) {
    $qstring = $this->params;
    $active_items = $this->adapter->getActiveItems($facet);

    // Appends to qstring if inactive, removes if active.
    foreach ($values as $value) {
      if ($active && isset($active_items[$value])) {
        unset($qstring[$this->filterKey][$active_items[$value]['pos']]);
      }
      elseif (!$active) {
        $field_alias = rawurlencode($facet['field alias']);
        $qstring[$this->filterKey][] = $field_alias . ':' . $value;
      }
    }

    // Removes duplicates, resets array keys and returns query string.
    // @see http://drupal.org/node/1340528
    $qstring[$this->filterKey] = array_values(array_unique($qstring[$this->filterKey]));
    return array_filter($qstring);
  }

  /**
   * Implements FacetapiUrlProcessor::setBreadcrumb().
   */
  public function setBreadcrumb() {
    // Don't set breadcrumb for bean blocks.
    if (isset($this->bean)) {
      return;
    }
    else {
      return parent::setBreadcrumb();
    }
  }

  /**
   * Helper function to generate date range for date facets.
   */
  protected function generateDateRange($range) {
    if ($range['date_range_start_op'] == 'NOW') {
      $lower = apachesolr_date_iso(time());
    }
    elseif ($range['date_range_start_op'] === 0) {
      $lower = apachesolr_date_iso(strtotime('-100 year'));
    }
    else {
      $lower = apachesolr_date_iso(strtotime($range['date_range_start_op'] . $range['date_range_start_amount'] . ' ' . $range['date_range_start_unit']));
    }
    if ($range['date_range_end_op'] == 'NOW') {
      $upper = apachesolr_date_iso(strtotime('+1 day'));
    }
    elseif ($range['date_range_end_op'] === 0) {
      $upper = apachesolr_date_iso(strtotime('+100 year'));
    }
    else {
      $upper = apachesolr_date_iso(strtotime($range['date_range_end_op'] . $range['date_range_end_amount'] . ' ' . $range['date_range_end_unit']));
    }

    return "[$lower TO $upper]";
  }
}
