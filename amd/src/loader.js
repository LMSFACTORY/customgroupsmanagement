define(['jquery'], function($) {
    return {
        init: function() {
            $(window).on('beforeunload', function() {
                $('#loader-overlay').removeClass('d-none');
            });
        }
    };
});
