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
          $conditions['f'][] = $data['field'] . ':' . $data['default_value'];
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
}
