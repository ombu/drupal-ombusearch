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

      }

    };

    Drupal.solrBean = {};

    Drupal.solrBean.filterChangeHandler = function(e) {
      var val = $(this).val();
      if (val != '') {
        window.location = $(this).val();
      }
    }

})(jQuery);
