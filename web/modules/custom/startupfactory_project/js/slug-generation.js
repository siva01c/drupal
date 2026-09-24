(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.slugGeneration = {
    attach: function (context, settings) {
      var $nameField = $(context).find('.js-project-name input');
      var $slugField = $(context).find('.js-project-slug input');

      if ($nameField.length && $slugField.length) {
        $nameField.on('input', function () {
          if (!$slugField.data('user-edited')) {
            var slug = $nameField.val()
              .toLowerCase()
              .replace(/[^a-z0-9]+/g, '-')
              .replace(/^-+|-+$/g, '');
            $slugField.val(slug);
          }
        });

        $slugField.on('input', function () {
          $slugField.data('user-edited', true);
        });
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
