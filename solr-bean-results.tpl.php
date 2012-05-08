<?php

/**
 * @file
 * Default theme implementation for displaying bean solr results.
 *
 * Available variables:
 * - $search_results: All results as it is rendered through
 *   search-result.tpl.php or node view mode.
 *
 *
 * @see template_preprocess_search_results()
 */
?>
<?php if ($search_results): ?>
  <ol class="search-results">
    <?php print $search_results; ?>
  </ol>
  <?php if (isset($pager)): ?>
    <?php print $pager; ?>
  <?php endif ?>
<?php else : ?>
  <h2><?php print t('Your search yielded no results');?></h2>
  <?php print search_help('search#noresults', drupal_help_arg()); ?>
<?php endif; ?>
