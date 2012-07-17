(function($) {

    Drupal.behaviors.solrBeanSelectWidget = {

      attach: function(context) {

        if (Drupal.settings.solrBeanSelectWidget.length) {

          var changeFn = function() {
            var val = $(this).val();
            if (val != '') {
              window.location = $(this).val();
            }
          };
          $.each(Drupal.settings.solrBeanSelectWidget, function(index, facet) {
            if (facet.initialized) {
              return;
            }

            var $el = $(facet.selector);
            $('.element-invisible', $el).remove();
            if ($('.facetapi-active', $el).length) {
              // Active
              var html = $('.facetapi-active', $el).parent('li').html();
              $el.replaceWith('<div class="facetapi-active-widget">' + html + '</div>');
            }
            else {
              // Dropdown
              var $select = $('<select></select>');
              $select.append('<option value="">- ' + facet.title + ' -</option>');
              $('a.facetapi-inactive', $el).each(function() {
                var $a = $(this);
                $select.append('<option value="' + $a.attr('href') + '">' + $a.text() + '</option>');
              });
              $select.bind('change', changeFn);
              $el.replaceWith($select);
            }

            facet.initialized = true;
          });

        }

      }

    };

})(jQuery);
