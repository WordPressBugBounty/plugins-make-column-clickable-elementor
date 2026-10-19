jQuery( function( $ ) {
  var control = '.elementor-control-make_column_clickable_promo';

  $( document ).on( 'click', control + ' .elementor-control-notice-dismiss', function( e ) {
    e.preventDefault();

    // Hide it for the rest of this editor session, the control is already in the panel config.
    $( '<style>' + control + '{display:none!important}</style>' ).appendTo( 'head' );

    $.post( makeColumnClickable.ajaxUrl, {
      action: 'make_column_clickable_dismiss_promo',
      nonce: makeColumnClickable.nonce
    } );
  } );
} );
