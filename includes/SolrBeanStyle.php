<?php

/**
 * @file
 * Base solr search style.
 */

class SolrBeanStyle extends BeanStyle {
  /**
   * Results returned from solr.
   *
   * @param array
   */
  protected $results;

  /**
   * Associated params from solr query, including total results found.
   *
   * @param array
   */
  protected $query_params;

  /**
   * Implements parent::prepareView().
   */
  public function prepareView($build, $bean) {
    $this->results = $build['search']['search_results']['#results'];

    $build = parent::prepareView($build, $bean);

    $this->processParams($build);

    $build['search']['search_results']['#theme'] = 'solr_bean_results';
    $build['search']['search_results']['#bean'] = $bean;
    $build['search']['search_results']['#search_results'] = $this->items;
    $build['search']['search_results']['#description'] = $this->getDescription($build);
    $build['search']['search_results']['#pager'] = $this->getPager($build);

    return $build;
  }

  /**
   * Implements parent::prepareItems().
   */
  protected function prepareItems($build, $type) {
    foreach ($this->results as $result) {
       $this->items[] = array(
        '#theme' => 'search_result',
        '#result' => $result,
        '#module' => 'apachesolr_search',
      );
    }
  }

  /**
   * Retrieve the associated query params from solr run.
   *
   * Includes information on current page and total results.
   *
   * @param array $build
   *   The bean build array
   */
  protected function processParams($build) {
    // Fetch our current query.
    $env_id = NULL;
    if (!empty($build['search']['search_results']['#search_page']['env_id'])) {
      $env_id = $build['search']['search_results']['#search_page']['env_id'];
    }
    $query = apachesolr_current_query($env_id);

    if ($query) {
      $response = apachesolr_static_response_cache($query->getSearcher());
    }

    if (empty($response)) {
      return;
    }

    $this->query_params = $query->getParams();
    $this->query_params['total'] = $response->response->numFound;

  }

  /**
   * Returns the current/total count for a query for title description.
   *
   * @param array $build
   *   The bean build array
   *
   * @return string
   *   The localized count description.
   */
  protected function getDescription($build) {
    return t('Showing items @start through @end of @total.', array(
      '@start' => $this->query_params['start'] + 1,
      '@end' => $this->query_params['start'] + $this->query_params['rows'] - 1,
      '@total' => $this->query_params['total'],
    ));
  }

  /**
   * Returns pager information if set to display by bean.
   *
   * @param array $build
   *   The bean build array
   *
   * @return string
   *   The themed pager string.
   */
  protected function getPager($build) {
    if ($this->bean->settings['pager']) {
      pager_default_initialize($this->query_params['total'], $this->query_params['rows'], $this->bean->settings['pager_element']);
      return theme('pager', array('tags' => NULL, 'element' => $this->bean->settings['pager_element']));
    }

    return '';
  }
}
