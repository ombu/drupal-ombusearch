(function($) {

    Drupal.behaviors.solrBeanSelectWidget = {

      attach: function(context) {

        if (typeof Drupal.settings.solrBeanSelectWidget !== 'undefined') {

          $.each(Drupal.settings.solrBeanSelectWidget, function(index, facet) {
            var $el = $(facet.selector, context);
            if ($el.hasClass('solr-bean-select-processed')) {
              return;
            }
            $el.addClass('solr-bean-select-processed');

            $('.element-invisible', $el).remove();
            if ($('.facetapi-active', $el).length || facet.type == 'or') {
              $('a', $el).bind('click', Drupal.solrBean.filterChangeHandler);
            }
            else {
              // Dropdown
              var $select = $('<select></select>');
              $select.append('<option value="">- ' + facet.title + ' -</option>');
              $('a.facetapi-inactive', $el).each(function() {
                var $a = $(this);
                $select.append('<option value="' + $a.attr('href') + '">' + $a.text() + '</option>');
              });
              $select.bind('change', Drupal.solrBean.filterChangeHandler);
              $el.replaceWith($select);
            }

          });

        }
        // Handler for form keys.
        $('.solr-bean-search-form', context).bind('submit', Drupal.solrBean.keysSubmitHandler);

        // Handler for changing number of results per page.
        $('.results-per-page', context).bind('change', Drupal.solrBean.resultsChangeHandler);

        // Handler for pagination.
        $('.pagination a').bind('click', Drupal.solrBean.paginationHandler);
      }

    };

    Drupal.behaviors.solrBeanState = {
      attach: function(context) {

        if ($('body').hasClass('solr-bean-statechange')) {
          return;
        }
        else {
          $('body').addClass('solr-bean-statechange');
        }
        $(window).bind('statechange', function() {
          var data = jQuery.deparam.querystring(window.location.search);
          $('.solr-bean').each(function(i, beanNode) {
            Drupal.solrBean.ajaxCall(data, $(beanNode));
          });
        });
      }
    };

    Drupal.solrBean = {};

    Drupal.solrBean.resultsChangeHandler = function(e) {
      if (e) {
        e.preventDefault();
      }

      var bean = $(this).closest('[data-module="bean"][data-delta]');

      // Check if facets are on the page, otherwise create an empty data object.
      var data = {};
      if ($('.solr-block-search-form', bean).length > 0) {
        data = $.deparam.querystring($('.solr-block-search-form', bean).action);
      }

      data.results_per_page = $(this).val();
      Drupal.solrBean.updateState(data, this);
    }

    Drupal.solrBean.paginationHandler = function(e) {
      if (e) {
        e.preventDefault();
      }

      var data = $.deparam.querystring(this.search);
      Drupal.solrBean.updateState(data, this);
    }

    Drupal.solrBean.filterChangeHandler = function(e) {
      if (e) {
        e.preventDefault();
      }

      var data = $.deparam.querystring(this.search);
      Drupal.solrBean.updateState(data, this);
    }

    Drupal.solrBean.keysSubmitHandler = function(e) {
      if (e) {
        e.preventDefault();
      }

      var data = $.deparam.querystring(this.action);
      Drupal.solrBean.updateState(data, this);
    };

    Drupal.solrBean.updateState = function(data, context) {
      var bean = $(context).closest('[data-module="bean"][data-delta]');

      data.keys = $('input[name="keys"]', bean).val();
      if (!data.results_per_page) {
        data.results_per_page = $('select[name="results_per_page"]', bean).val();
      }

      History.pushState(data, '', window.location.pathname + '?' + $.param(data));
    };

    Drupal.solrBean.ajaxCall = function(data, bean) {
      bean.addClass('ajax-processing');

      jQuery.ajax(Drupal.settings.basePath + 'solr_bean/' + bean.attr('data-delta'), {
        data: data,
        dataType: 'html',
        context: bean
      })
      .done(Drupal.solrBean.ajaxComplete);
    }

    Drupal.solrBean.ajaxComplete = function(data) {
      $('.bean-solr-bean', this).replaceWith(data);
      Drupal.attachBehaviors($('.bean-solr-bean', this));

      $(this).removeClass('ajax-processing');
    };

})(jQuery);
