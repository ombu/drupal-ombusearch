<?php

class FacetapiDatepicker extends FacetapiWidget {

  public function __construct($id, array $realm, FacetapiFacet $facet, stdClass $settings) {
    parent::__construct($id, $realm, $facet, $settings);
    $this->key = $facet['name'];
  }

  /**
   * Renders the form.
   */
  public function execute() {
    $element = &$this->build[$this->facet['field alias']];
    reset($element);
    $time = key($element);
    if (count($element) == 1 && !empty($element[$time]['#active'])) {
      preg_match('/\[(.*) TO (.*)\]/', $time, $matches);

      list($start) = explode('T', $matches[1]);
      list($end) = explode('T', $matches[2]);

      $text = date('M j, Y', strtotime($start));

      if ($start != $end) {
        $text .= ' to ' . date('M j, Y', strtotime($end));
      }
      $element = array(
        '#prefix' => '<h4>' . t('Event date') . '</h4>',
        '#theme' => 'link',
        '#text' => $text,
        '#path' => $element[$time]['#path'],
        '#options' => array(
          'html' => FALSE,
          'query' => $element[$time]['#query'],
          'attributes' => array(
            'class' => array('remove-filter'),
          ),
        ),
      );
    }
    else {
      $name = explode(':', $this->build['#settings']->name);
      $name = array_pop($name);
      $element = drupal_get_form('facetapi_datepicker_' . $name, $element, $this->build['#facet']['field']);
    }
  }

}

/**
 * Generate form for facet.
 */
function facetapi_datepicker($form, &$form_state, $elements, $field) {
  if (count($elements) == 1) {
    $time = key($elements);
    preg_match('/\[(.*) TO (.*)\]/', $time , $matches);
    $start = date('Y-m-d', strtotime($matches[1]));
    $end = date('Y-m-d', strtotime($matches[2]));
  }
  $form['date_group'] = array(
    '#type' => 'fieldset',
    '#title' => 'Event date',
    '#collapsed' => FALSE,
    '#collapsable' => FALSE,
  );

  $form['date_group']['instructions'] = array(
    '#markup' => '<div class="instructions"><p>' . t('Select a day or range of days.') . '</p></div>',
  );

  $form['date_group']['start'] = array(
    '#type' => 'textfield',
    '#title' => t('From'),
    '#default_value' => isset($start) ? $start : NULL,
    '#attributes' => array(
      'placeholder' => 'YYYY-MM-DD',
    ),
  );

  $form['date_group']['end'] = array(
    '#type' => 'textfield',
    '#title' => t('To'),
    '#default_value' => isset($end) ? $end : NULL,
    '#attributes' => array(
      'placeholder' => 'YYYY-MM-DD',
    ),
  );

  $form['field'] = array(
    '#type' => 'hidden',
    '#value' => $field,
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#buttontype' => 'button',
    '#value' => '<span class="single">' . t('Apply date') . '</span><span class="separator"> / </span><span class="multiple">' . t('Apply date range') . '</span>',
    '#submit' => array('facetapi_datepicker_submit'),
  );

  $form['#attributes']['class'][] = 'facetapi-datepicker';

  return $form;
}

/**
 * Submit handler for facet form.
 */
function facetapi_datepicker_submit($form, &$form_state) {
  $form_state['values']['end'] .= 'T23:59:59Z';
  $form_state['values']['start'] .= 'T00:00:00Z';

  $iso_start = $form_state['values']['start'];
  $iso_end   = $form_state['values']['end'];
  $field     = $form_state['values']['field'];

  // TODO: find a better was for handling the redirect arguments.
  $query = array('f' => array());
  if(!empty($_GET['f'])) {
    $query['f'] = $_GET['f'];
    foreach ($_GET['f'] as $key => $part) {
      if(strpos($part, $field) === 0) {
        unset($_GET['f'][$key]);
      }
    }
  }
  $query['f'][] = $field . ':[' . $iso_start . ' TO ' . $iso_end . ']';
  $form_state['redirect'] = array($_GET['q'], array('query' => $query));
}
