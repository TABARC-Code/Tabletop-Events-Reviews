/**
 * Tabletop Events Calendar — Reviews — organiser aggregate widget.
 * Read-only: an organiser's reputation across every event of theirs,
 * anchored on any one of their own event IDs. No submit form here —
 * reviews are always left against a specific event, via
 * [tabletop_event_reviews].
 */
(function () {
	'use strict';

	var REST = ( window.TEVR_ORGANISER_REVIEWS && window.TEVR_ORGANISER_REVIEWS.restUrl ) || '/wp-json/tevr/v1';
	var ANCHOR_ID = ( window.TEVR_ORGANISER_REVIEWS && window.TEVR_ORGANISER_REVIEWS.anchorId ) || 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tevr-organiser-reviews]' ).forEach( init );
	} );

	function init( root ) {
		root.innerHTML = '<div class="tevr-empty">Loading…</div>';

		fetch( REST + '/organiser/' + ANCHOR_ID )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data.count ) {
					root.innerHTML = '<p class="tevr-empty">No reviews yet.</p>';
					return;
				}
				root.innerHTML =
					'<p class="tevr-summary"><span class="tevr-average">' + data.average.toFixed( 1 ) + '★</span> from ' + data.count + ( data.count === 1 ? ' review' : ' reviews' ) + '</p>' +
					'<div class="tevr-list">' + data.reviews.map( reviewItem ).join( '' ) + '</div>';
			} )
			.catch( function () {
				root.innerHTML = '<div class="tevr-empty">Could not load reviews.</div>';
			} );
	}

	function reviewItem( r ) {
		return (
			'<div class="tevr-review">' +
				'<div class="tevr-review-top"><span class="tevr-stars">' + stars( r.rating ) + '</span><span class="tevr-name">' + escapeHtml( r.name ) + '</span></div>' +
				( r.eventTitle ? '<div class="tevr-event-title">' + escapeHtml( r.eventTitle ) + '</div>' : '' ) +
				'<p class="tevr-comment">' + escapeHtml( r.comment ) + '</p>' +
			'</div>'
		);
	}
	function stars( n ) {
		return '★★★★★☆☆☆☆☆'.slice( 5 - n, 10 - n );
	}
	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}
})();
