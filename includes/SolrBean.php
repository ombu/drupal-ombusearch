<?php

/**
 * @file
 * Defines the VaSearchSolr class.
 */

class SolrBean extends ombubeans_color {

  /**
   * The highest pager element we've autogenerated so far.
   *
   * @var int
   */
  static $maxPagerElement = 0;

  /**
   * Implements BeanPlugin::values().
   */
  public function values() {
    $values = parent::values();

    $values += array(
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
    $form['results_view_mode'] = array(
      '#title' => t('Results View Mode'),
      '#type' => 'select',
      '#options' => solr_bean_view_modes(),
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

    $search_page = apachesolr_search_page_load($bean->search_page);
    $form['settings']['results_per_page'] = array(
      '#type' => 'select',
      '#title' => t('Number of results to show'),
      '#default_value' => isset($bean->settings['results_per_page']) ? $bean->settings['results_per_page'] : $search_page->settings['apachesolr_search_per_page'],
      '#options' => array(
        10 => 10,
        20 => 20,
        30 => 30,
        40 => 40,
        50 => 50,
      ),
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
    // If this bean has paging enabled, set a unique pager element.
    if ($bean->settings['pager']) {
      $bean->settings['pager_element'] = SolrBean::$maxPagerElement++;
    }

    // Set the bean for later use.
    $this->setBean($bean);

    $search_page = apachesolr_search_page_load($bean->search_page);
    if (empty($search_page)) {
      drupal_set_message(t('This search page cannot be found'), 'error');
      return $content;
    }

    // Retrieve the conditions that apply to this block.
    $conditions = apachesolr_search_conditions_default($search_page);
    $conditions['f'] = array();
    $keys = isset($conditions['keys']) ? $conditions['keys'] : $bean->facets['keys']['default_value'];

    // Set the sort field and order.
    $conditions['apachesolr_search_sort'] = $bean->sort['field'] . ' ' . $bean->sort['order'];

    // Retrieve the results of the search.
    $results = $this->doSearch($keys, $conditions, $search_page);

    // Initiate our build array.
    $build = array();

    // Suppress all solr blocks temporarily.
    $old_suppress = apachesolr_suppress_blocks($search_page->env_id);
    apachesolr_suppress_blocks($search_page->env_id, TRUE);

    // Build our page and allow modification.
    $build_results = apachesolr_search_search_page_custom($results, $search_page, $build);

    apachesolr_suppress_blocks($search_page->env_id, $old_suppress);

    // Render result in our custom theme, to allow for different display modes.
    $build_results['search_results']['#theme'] = array('solr_bean_results__' . $bean->results_view_mode, 'solr_bean_results');
    $build_results['search_results']['#bean'] = $bean;

    // Adds the search form to the page.
    $search_form = drupal_get_form('solr_bean_search_form', $this);
    if (isset($search_form['f']) || isset($search_form['keys'])) {
      $search_form['#weight'] = -10;
      $build_results['search_form'] = $search_form;
    }

    $content['search'] = $build_results;

    return $content;
  }

  /**
   * Performs a solr search, using custom facetapi adapter for solr bean.
   *
   * Borrowed from apachesolr_search_search_results().
   */
  protected function doSearch($keys, $conditions, $search_page) {
    // Sort options from the conditions array.
    // @see apachesolr_search_conditions_default()
    // See This condition callback to find out how.
    $solrsort = isset($conditions['apachesolr_search_sort']) ? $conditions['apachesolr_search_sort'] : '';

    try {
      $solr = apachesolr_get_solr($search_page->env_id);
      // Default parameters.
      $params['fq'] = isset($conditions['fq']) ? $conditions['fq'] : array();

      // Set the number of rows from the bean.
      $params['rows'] = isset($this->bean->settings['results_per_page']) ? $this->bean->settings['results_per_page'] : $search_page->settings['apachesolr_search_per_page'];

      if (empty($search_page->settings['apachesolr_search_spellcheck'])) {
        // Spellcheck needs to have a string as false/true
        $params['spellcheck'] = 'false';
      }
      else {
        $params['spellcheck'] = 'true';
      }

      // Always show potential results.  Normal apache solr results will show
      // either the facet browser or a blank page if there aren't keys or facets
      // selected.
      $params['q'] = $keys;
      // This is the object that knows about the query coming from the user.
      $page = $this->bean->settings['pager'] ? pager_find_page() : 0;

      $query = apachesolr_drupal_query('apachesolr', $params, $solrsort, $search_page->search_path, $solr);
      apachesolr_search_basic_params($query);

      if ($query->getParam('q')) {
        apachesolr_search_add_spellcheck_params($query);
      }

      // Add custom params after we add the default params.
      $query->addParams($params);

      // Add the paging parameters.
      $query->page = $page;

      apachesolr_search_add_boost_params($query);
      if ($query->getParam('q')) {
        apachesolr_search_highlighting_params($query);
        if (!$query->getParam('hl.fl')) {
          $qf = array();
          foreach ($query->getParam('qf') as $field) {
            // Truncate off any boost so we get the simple field name.
            $parts = explode('^', $field, 2);
            $qf[$parts[0]] = TRUE;
          }
          foreach (array('content', 'ts_comments') as $field) {
            if (isset($qf[$field])) {
              $query->addParam('hl.fl', $field);
            }
          }
        }
      }
      else {
        // No highlighting, use the teaser as a snippet.
        $query->addParam('fl', 'teaser');
      }

      list($final_query, $response) = $this->doQuery($query, $page);
      $solr_id = $query->solr('getId');
      apachesolr_has_searched($solr_id, TRUE);
      return $this->processResponse($response, $final_query);
    }
    catch (Exception $e) {
      watchdog('Apache Solr', nl2br(check_plain($e->getMessage())), NULL, WATCHDOG_ERROR);
      apachesolr_failure(t('Solr search'), $keys);
    }
    return $results;
  }

  /**
   * Performs the actual solr query.  Borrowed from apachesolr_do_query().
   */
  protected function doQuery(DrupalSolrQueryInterface $current_query) {
    if (!is_object($current_query)) {
      throw new Exception(t('NULL query object in function apachesolr_do_query()'));
    }
    // Allow modules to alter the query prior to statically caching it.
    // This can e.g. be used to add available sorts.
    $searcher = $current_query->getSearcher();

    if (module_exists('facetapi')) {
      // Gets enabled facets, adds filter queries to $params.
      // Here's where the magic happens for solr_bean, since we can use our own
      // adapter.
      $adapter = facetapi_adapter_load($searcher);
      if ($adapter) {
        $adapter->getUrlProcessor()->setBean($this->bean);

        // Fetches, normalizes, and sets filter params.
        $filter_key = $adapter->getUrlProcessor()->getFilterKey();
        $params = $adapter->getUrlProcessor()->fetchParams();
        $adapter->setParams($params, $filter_key);

        // Realm could be added but we want all the facets.
        $adapter->addActiveFilters($current_query);
      }
    }

    foreach (module_implements('apachesolr_query_prepare') as $module) {
      $function_name = $module . '_apachesolr_query_prepare';
      $function_name($current_query);
    }

    // Cache the original query. Since all the built queries go through
    // this process, all the hook_invocations will happen later.
    $env_id = $current_query->solr('getId');

    // Add our defType setting here. Normally this would be dismax or the
    // setting from the solrconfig.xml. This allows the setting to be
    // overridden.
    $def_type = apachesolr_environment_variable_get($env_id, 'apachesolr_query_type');
    if (!empty($def_type)) {
      $current_query->addParam('defType', $def_type);
    }

    $query = apachesolr_current_query($env_id, $current_query);

    // This hook allows modules to modify the query and params objects.
    drupal_alter('apachesolr_query', $query);

    if ($query->abort_search) {
      // A module implementing HOOK_apachesolr_query_alter() aborted the search.
      return array(NULL, array());
    }
    $query->addParam('start', $query->page * $query->getParam('rows'));

    $keys = $query->getParam('q');

    if (strlen($keys) == 0 && ($filters = $query->getFilters())) {
      // Move the fq params to q.alt for better performance. Only suitable
      // when using dismax or edismax, so we keep this out of the query class
      // itself for now.
      $qalt = array();
      foreach ($filters as $delta => $filter) {
        // Move the fq param if it has no local params and is not negative.
        if (!$filter['#exclude'] && !$filter['#local']) {
          $qalt[] = '(' . $query->makeFilterQuery($filter) . ')';
          $query->removeFilter($filter['#name'], $filter['#value'], $filter['#exclude']);
        }
      }
      if ($qalt) {
        $query->addParam('q.alt', implode(' ', $qalt));
      }
    }
    // We must run htmlspecialchars() here since converted entities are in the
    // index and thus bare entities &, > or < won't match. Single quotes are
    // converted too, but not double quotes since the dismax parser looks at
    // them for phrase queries.
    $keys = htmlspecialchars($keys, ENT_NOQUOTES, 'UTF-8');
    $keys = str_replace("'", '&#039;', $keys);
    $response = $query->search($keys);
    // The response is cached so that it is accessible to the blocks and
    // anything else that needs it beyond the initial search.
    apachesolr_static_response_cache($searcher, $response);
    return array($query, $response);
  }

  /**
   * Process solr results.  Borrowed from apachesolr_search_process_response().
   *
   * Need to override to handle unique pagers.
   */
  protected function processResponse($response, DrupalSolrQueryInterface $query) {
    $results = array();
    // We default to getting snippets from the body content and comments.
    $hl_fl = $query->getParam('hl.fl');
    if (!$hl_fl) {
      $hl_fl = array('content', 'ts_comments');
    }
    $total = $response->response->numFound;

    // Initialize pager, with pager element, if needed.
    if ($this->bean->settings['pager']) {
      pager_default_initialize($total, $query->getParam('rows'), $this->bean->settings['pager_element']);
    }


    if ($total > 0) {
      $fl = $query->getParam('fl');
      // 'id' and 'entity_type' are the only required fields in the schema, and
      // 'score' is generated by solr.
      // @todo: here is where we should handle different display modes, instead
      // of SorlBean::view().
      foreach ($response->response->docs as $doc) {
        $extra = array();

        // Start with an empty snippets array.
        $snippets = array();

        // Find the nicest available snippet.
        foreach ($hl_fl as $hl_param) {
          if (isset($response->highlighting->{$doc->id}->$hl_param)) {
            // Merge arrays preserving keys.
            foreach ($response->highlighting->{$doc->id}->$hl_param as $values) {
              $snippets[$hl_param] = $values;
            }
          }
        }
        // If there's no snippet at this point, add the teaser.
        if (!$snippets) {
          if (isset($doc->teaser)) {
            $snippets[] = truncate_utf8($doc->teaser, 256, TRUE);
          }
        }
        $hook = 'apachesolr_search_snippets__' . $doc->entity_type;
        if (!empty($doc->bundle)) {
          $hook .= '__' . $doc->bundle;
        }
        $snippet = theme($hook, array('doc' => $doc, 'snippets' => $snippets));

        if (!isset($doc->content)) {
          $doc->content = $snippet;
        }

        // Normalize common dates so that we can use Drupal's normal date and
        // time handling.
        if (isset($doc->ds_created)) {
          $doc->created = strtotime($doc->ds_created);
        }
        else {
          $doc->created = NULL;
        }

        if (isset($doc->ds_changed)) {
          $doc->changed = strtotime($doc->ds_changed);
        }
        else {
          $doc->changed = NULL;
        }

        if (isset($doc->tos_name)) {
          $doc->name = $doc->tos_name;
        }
        else {
          $doc->name = NULL;
        }

        $extra = array();

        // Allow modules to alter each document and its extra information.
        drupal_alter('apachesolr_search_result', $doc, $extra, $query);

        // Set all expected fields from fl to NULL if they are missing so
        // as to prevent Notice: Undefined property.
        foreach ($fl as $field) {
          if (!isset($doc->{$field})) {
            $doc->{$field} = NULL;
          }
        }

        $fields = (array) $doc;

        $result = array(
          // 'link' is a required field, so handle it centrally.
          'link' => url($doc->path, array('absolute' => TRUE)),
          // template_preprocess_search_result() runs check_plain() on the title
          // again.  Decode to correct the display.
          'title' => htmlspecialchars_decode($doc->label, ENT_QUOTES),
          // These values are not required by the search module but are provided
          // to give entity callbacks and themers more flexibility.
          'score' => $doc->score,
          'snippets' => $snippets,
          'snippet' => $snippet,
          'fields' => $fields,
          'entity_type' => $doc->entity_type,
          'bundle' => $doc->bundle,
        );

        // Call entity-type-specific callbacks for extra handling.
        $function = apachesolr_entity_get_callback($doc->entity_type, 'result callback');
        if (is_callable($function)) {
          $function($doc, $result, $extra);
        }

        $result['extra'] = $extra;

        $results[] = $result;
      }

      // Hook to allow modifications of the retrieved results.
      foreach (module_implements('apachesolr_process_results') as $module) {
        $function = $module . '_apachesolr_process_results';
        $function($results, $query);
      }
    }
    return $results;
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
      if (isset($this->bean->facets[$facet['name']]) && $this->bean->facets[$facet['name']]['visible']) {
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
