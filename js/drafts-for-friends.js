(function ($) {

  var client = new ZeroClipboard( $( '.copy-to-clipboard' ) );

  client.on( 'mouseover', function ( e ) {
    var target = $( e.target );
    target.parents( 'div.row-actions' ).addClass('visible');
  });

  client.on( 'mouseout', function ( e ) {
    var target = $( e.target );
    target.parents( 'div.row-actions' ).removeClass('visible');
  });

  client.on( 'aftercopy', function ( e ) {
    var target = $( e.target );
    var message = target.parents('.post_title').find('.copied');

    message.addClass( 'show' );

    setTimeout(function (){
      message.removeClass( 'show' );
    }, 1000);

  });

  $( '.extend-limit' ).on( 'click' , function (e) {
    e.preventDefault();
    var hash = $( this ).data( 'hash' );
    var form = $( '#draftsforfriends-extend-form-' + hash );

    form.show();
  });

  $('.draftsforfriends-extend-cancel').on('click', function (e){
    e.preventDefault();
    $(this).parents('form').hide();
  });

})(jQuery);