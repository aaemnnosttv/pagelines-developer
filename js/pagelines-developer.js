jQuery(document).ready(function( $ ) {
	$ctrigger = $('#wp-admin-bar-pl_constants > a');
	$modal    = $('#pld_modal');
	$close    = $('.media-modal-close, .media-modal-backdrop');

	$('.const-data', $modal)
		.filter(':odd')
		.addClass('alternate');

	$modal.dialog({
      autoOpen: false,
      show: {
        effect: "blind",
        duration: 200
      },
      hide: {
        effect: "blind",
        duration: 200
      }
    });

	$ctrigger.click( function() {
		$modal.dialog( "open" );
	});
	$close.click( function() {
		$modal.dialog( "close" );
	});
});