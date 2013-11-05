Solr Bean
=========

Provides a new bean type that allows content editors to create new blocks that
pull faceted results directly from solr.

Requirements:

  - apachesolr.module (tested with 7.x-1.0-rc3)
  - apachesolr_pages.module (tested with 7.x-1.0-rc3)
  - facetapi.module

Usage
-----

  1. Make sure there is at least one search page setup using apachesolr_pages.
  2. Enable any facets you wish to be exposed to block editors in the solr
     environment.
  3. Create a new Search Block bean.
