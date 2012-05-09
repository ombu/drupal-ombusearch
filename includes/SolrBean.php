<?php

/**
 * @file
 * Defines the VaSearchSolr class.
 */

class SolrBean extends BeanPlugin {
  /**
   * Implements BeanPlugin::values().
   */
  public function values() {
    $values = parent::values();

    $values = array(
      // @todo: remove default for testing.
      'search_page' => 'core_search',
      'facets' => array(),
      'settings' => array(
        'pager' => 1,
      ),
      'results_view_mode' => 'solr',
      'sort' => array(
        'field' => 'score',
        'order' => 'desc',
      ),
    );
    return $values;
  }

  /**
   * Implements BeanPlugin::form().
   */
  public function form($bean, $form, &$form_state) {
    $form = parent::form($bean, $form, $form_state);

    // Set the bean for later use.
    $this->setBean($bean);

    // Let the user choose which view mode the results should be displayed as.
    $entity_info = entity_get_info('node');
    $view_mode_options = array();
    foreach ($entity_info['view modes'] as $machine_name => $info) {
      if ($info['custom settings'] === TRUE) {
        $view_mode_options[$machine_name] = $info['label'];
      }
    }
    $form['results_view_mode'] = array(
      '#title' => t('Results View Mode'),
      '#type' => 'select',
      '#options' => array('solr' => t('Search results')) + $view_mode_options,
      '#description' => 'Select how you would like the node results to be displayed',
      '#default_value' => $bean->results_view_mode,
    );

    // @todo: make search page configurable, and show facets dynamically based
    // on which search page is selected.  For now, just default to core_search.
    if (FALSE) {
      // Get a list of all solr search pages, for use in the block.
      $search_pages = apachesolr_search_load_all_search_pages();
      $options = array();
      foreach ($search_pages as $key => $page) {
        $options[$key] = $page['label'];
      }

      $form['search_page'] = array(
        '#type' => 'select',
        '#title' => t('Search type'),
        '#description' => t('Select the type of search you want to display'),
        '#options' => $options,
        '#required' => TRUE,
        '#default_value' => $bean->search_page,
      );
    }
    else {
      $form['search_page'] = array(
        '#type' => 'value',
        '#default_value' => $bean->search_page,
      );
    }

    // Get search facets for the given search page.
    module_load_include('inc', 'solr_bean', 'solr_bean.callbacks');
    $facets = $this->getFacetInfo();

    // Add search field as an additional "facet", even though it's just a search
    // form.
    $facets += array(
      'keys' => array(
        'label' => t('Search Keys'),
        'description' => t('Filter by search keywords.'),
        'map callback' => '',
        'values callback' => '',
      ),
    );

    // Form for facet visibility, default value settings.
    $form['facets'] = array(
      '#theme' => 'solr_bean_facet_settings_table',
      '#tree' => TRUE,
    );
    foreach ($facets as $key => $facet) {
      // Set sane defaults for each facet option.
      $options = isset($bean->facets[$key]) ? $bean->facets[$key] : array(
        'visible' => 0,
        'weight' => 0,
        'default_value' => '',
      );
      $form['facets'][$key]['#facet'] = $facet;

      $form['facets'][$key]['visible'] = array(
        '#type' => 'checkbox',
        '#title' => t('Visibility for @title', array('@title' => $facet['label'])),
        '#title_display' => 'invisible',
        '#default_value' => $options['visible'],
      );

      // Set the values callback for taxonomy terms, since it's no longer being
      // set.
      if ($facet['map callback'] == 'facetapi_map_taxonomy_terms') {
        $facet['values callback'] = 'facetapi_callback_taxonomy_values';
      }

      // Get available values for a facet, if exists.
      if (!empty($facet['values callback']) && function_exists($facet['values callback'])) {
        $form['facets'][$key]['default_value'] = array(
          '#type' => 'select',
          '#title' => t('Default value'),
          '#title_display' => 'invisible',
          '#options' => array('' => '- None -') + call_user_func($facet['values callback'], $facet),
          '#default_value' => $options['default_value'],
        );
      }
      // Otherwise just show a simple textfield.
      else {
        $form['facets'][$key]['default_value'] = array(
          '#type' => 'textfield',
          '#title' => t('Default value'),
          '#title_display' => 'invisible',
          '#default_value' => $options['default_value'],
        );
      }
    }

    // Sort settings.
    $sort_fields = $this->getSortFieldOptions();
    $form['sort'] = array(
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => t('Sort Order'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      'field' => array(
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => t('Sort By'),
        '#options' => $this->getSortFieldOptions(),
        '#default_value' => $bean->sort['field'],
      ),
      'order' => array(
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => t('Order'),
        '#options' => array(
          'desc' => 'Descending',
          'asc' => 'Ascending',
        ),
        '#default_value' => $bean->sort['order'],
      ),
    );

    // General settings for solr search.
    $form['settings'] = array(
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => t('Search settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['settings']['pager'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show Pager?'),
      '#default_value' => isset($bean->settings['pager']) ? $bean->settings['pager'] : 1,
    );

    return $form;
  }

  /**
   * Impelements BeanPlugin::submit().
   */
  public function submit(Bean $bean) {
    // Save the field id with each facet, so it doesn't have to be queryed
    // later.
    $this->setBean($bean);
    $facets = $this->getFacetInfo();
    foreach ($bean->facets as $key => $facet) {
      if ($key == 'keys') {
        continue;
      }
      $bean->data['facets'][$key]['field'] = $facets[$key]['field'];
    }
  }

  /**
   * Implements BeanPlugin::view().
   */
  public function view($bean, $content, $view_mode = 'default', $langcode = NULL) {
    // Set the bean for later use.
    $this->setBean($bean);

    $search_page = apachesolr_search_page_load($bean->search_page);
    if (empty($search_page)) {
      drupal_set_message(t('This search page cannot be found'), 'error');
      return $content;
    }

    // Get all defined filter query values from conditions.
    $filter_queries = array();
    if (!empty($_GET['f'])) {
      foreach ($_GET['f'] as $f) {
        list($key, $value) = explode(':', $f);
        $filer_queries[] = $key;
      }
    }

    // Set default values if needed for facets.
    foreach ($bean->facets as $key => $data) {
      if ($key == 'keys') {
        continue;
      }

      if (!empty($data['default_value']) && !in_array($data['field'], $filter_queries)) {
        $_GET['f'][] = $data['field'] . ':' . $data['default_value'];
      }
    }
    $keys = isset($_GET['keys']) ? $_GET['keys'] : $bean->facets['keys']['default_value'];

    // Retrieve the conditions that apply to this page.
    $conditions = apachesolr_search_conditions_default($search_page);

    // Set the sort field and order.
    $conditions['apachesolr_search_sort'] = $bean->sort['field'] . ' ' . $bean->sort['order'];

    // Retrieve the results of the search.
    $results = apachesolr_search_search_results($keys, $conditions, $search_page);

    // Initiate our build array.
    $build = array();

    // Store the current breadcrumb, so facetapit doesn't override it.
    $breadcrumb = drupal_get_breadcrumb();

    // Build our page and allow modification.
    $build_results = apachesolr_search_search_page_custom($results, $search_page, $build);

    // Render result in our custom theme, to allow for different display modes.
    $build_results['search_results']['#theme'] = 'solr_bean_results';
    $build_results['search_results']['#bean'] = $bean;

    // Alter results if alternate view mode is selected.
    if ($bean->results_view_mode != 'solr' && !empty($build_results['search_results']['#results'])) {
      $nids = array();
      foreach ($build_results['search_results']['#results'] as $result) {
        $nids[] = $result['node']->entity_id;
      }
      $build_results['search_results']['#results'] = node_view_multiple(node_load_multiple($nids), $bean->results_view_mode);
    }

    // Adds the search form to the page.
    $search_form = drupal_get_form('solr_bean_search_form', $this);
    if (isset($search_form['f']) || isset($search_form['keys'])) {
      $search_form['#weight'] = -10;
      $build_results['search_form'] = $search_form;
    }

    $content['search'] = $build_results;

    // And set the breadcrumb to the old value.
    drupal_set_breadcrumb($breadcrumb);

    return $content;
  }

  /**
   * Returns the FacetapiAdapter object.
   */
  protected function getFacetapiAdapter() {
    static $adapter = NULL;

    if (!$adapter) {
      $search_pages = apachesolr_search_load_all_search_pages();
      // @todo: figure out the correct way to set searcher string.
      $searcher = 'apachesolr@' . $search_pages[$this->bean->search_page]['env_id'];
      $adapter = facetapi_adapter_load($searcher);
    }

    return $adapter;
  }

  /**
   * Gets the available facets for a solr search realm (set in
   * $bean->search_page).
   */
  protected function getFacetInfo() {
    $adapter = $this->getFacetapiAdapter();
    $adapter->processFacets();
    return $adapter->getEnabledFacets();
  }

  /**
   * Builds full facet info on a search page.
   */
  public function buildFacets() {
    $realm = facetapi_realm_load('block');
    $adapter = $this->getFacetapiAdapter();
    $adapter->processFacets();

    $facets = array();
    foreach ($adapter->getEnabledFacets() as $facet) {
      if ($this->bean->facets[$facet['name']]['visible']) {
        $processor = $adapter->getProcessor($facet['name']);
        $facets[$facet['name']] = $this->buildFacetField($facet, $processor->getBuild());
      }
    }

    return $facets;
  }

  /**
   * Builds a facet field for use in the bean display form.
   */
  protected function buildFacetField($facet, $build) {
    $facet_values = array();
    if (isset($_GET['f'])) {
      foreach ($_GET['f'] as $f) {
        list($key, $value) = explode(':', $f);
        $facet_values[$key] = $value;
      }
    }
    $field = array();
    if ($build) {
      $options = array();
      foreach ($build as $key => $data) {
        $options[$key] = $data['#markup'];
      }
      $field = array(
        '#type' => 'select',
        '#title' => $facet['label'],
        '#options' => array('- Any - ') + $options,
        '#default_value' => isset($facet_values[$facet['name']]) ? $facet_values[$facet['name']] : $this->bean->facets[$facet['name']]['default_value'],
      );
    }
    return $field;
  }


  /**
   * Returns an array of field options.
   */
  protected function getSortFieldOptions() {
    // Defaults.
    $sort_fields = array(
      'score' => 'Score',
      'bs_sticky' => 'Sticky',
      'ds_created' => 'Creation Date',
      'ds_changed' => 'Updated Date',
      'bundle' => 'Node Type',
      'label' => 'Title',
    );
    foreach ($this->getFacetInfo() as $key => $facet) {
      $sort_fields[$facet['field']] = $facet['label'];
    }
    return $sort_fields;
  }
}
