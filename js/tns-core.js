/** Page Section Interactions
 *****************************/

( function( $, tns ) {
	tns.page_section = {
		section_switch: function ( ) {
			$( 'select[ name~=tns-section-handles ]' ).on( 'change', function ( ) {
				var index = $( this ).val()
					, $target = $( '#' + index )
					, $sections = $( '.tns-section-bodies > div' );
				if ( $target.length > 0 ) {
					$sections.removeClass( 'active' );
					$target.addClass( 'active' );
				}
			} );
		}
		, init: function ( ) {
			this.section_switch();
		}
	}
	tns.page_section.init();
} ) ( jQuery, window.tns = window.tns || {} );