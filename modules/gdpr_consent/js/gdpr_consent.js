(function ($) {

  /**
   * Manage the conflict between AJAX element updates and regular form
   * submission where the disabled AJAX element doesn't get submitted.
   */
  Drupal.behaviors.gdprConsentExpandDescription = {
    attach: function (context, settings) {
      // Hide the description for any GDPR checkboxes.
      var containers = $('.gdpr_consent_agreement', context).parent();

      containers.each(function () {
        var container = $(this).parent();
        var desc = container.find('.description', null, context);

        if (!desc.length) {
          return true;
        }

        desc.hide();

        $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>', context)
          .insertAfter(container.find('label', context))
          .click(function () {
            var desc = $(this, context).find('.description', context);
            if (!desc.length) {
              desc = $(this, context).parent().find('.description', context);
            }

            desc.slideToggle()
          });

      });


      // Do the same for implicit
      containers = $('.gdpr_consent_agreement', context).parent();

      containers.each(function () {
        var container = $(this).parent();
        desc = container.next('.description');


        if (!desc.length) {
          return true;
        }

        desc.hide();

        $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>', context)
          .appendTo(container)
          .click(function () {
            $(this).next('.description', context).slideToggle()
          });
      });



    }
  };

})(jQuery);
