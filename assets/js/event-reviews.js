/**
 * Tabletop Events Calendar — Reviews — single-event widget. Shows
 * approved reviews plus a "leave a review" form. The form is always
 * shown (an event that hasn't happened yet, or an email that isn't a
 * confirmed RSVP, gets a clear rejection message from the REST
 * endpoint rather than trying to predict eligibility client-side).
 */
(function () {
	'use strict';

	var REST = ( window.TEVR_EVENT_REVIEWS && window.TEVR_EVENT_REVIEWS.restUrl ) || '/wp-json/tevr/v1';
	var EVENT_ID = ( window.TEVR_EVENT_REVIEWS && window.TEVR_EVENT_REVIEWS.eventId ) || 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tevr-event-reviews]' ).forEach( init );
	} );

	function init( root ) {
		root.innerHTML = '<div class="tevr-empty">Loading…</div>';

		fetch( REST + '/event/' + EVENT_ID )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				root.innerHTML = summaryHtml( data ) + listHtml( data.reviews ) + formHtml();
				bind( root );
			} )
			.catch( function () {
				root.innerHTML = '<div class="tevr-empty">Could not load reviews.</div>';
			} );
	}

	function summaryHtml( data ) {
		if ( ! data.count ) {
			return '<p class="tevr-empty">No reviews yet — be the first.</p>';
		}
		return (
			'<p class="tevr-summary">' +
				'<span class="tevr-average">' + data.average.toFixed( 1 ) + '★</span>' +
				' from ' + data.count + ( data.count === 1 ? ' review' : ' reviews' ) +
			'</p>'
		);
	}

	function listHtml( reviews ) {
		return '<div class="tevr-list">' + reviews.map( reviewItem ).join( '' ) + '</div>';
	}

	function reviewItem( r ) {
		return (
			'<div class="tevr-review">' +
				'<div class="tevr-review-top"><span class="tevr-stars">' + stars( r.rating ) + '</span><span class="tevr-name">' + escapeHtml( r.name ) + '</span></div>' +
				'<p class="tevr-comment">' + escapeHtml( r.comment ) + '</p>' +
			'</div>'
		);
	}

	function stars( n ) {
		return '★★★★★☆☆☆☆☆'.slice( 5 - n, 10 - n );
	}

	function formHtml() {
		return (
			'<h3 class="tevr-form-heading">Leave a review</h3>' +
			'<p class="tec-sf-help">Only works if you had a confirmed RSVP for this event.</p>' +
			'<form class="tec-sf-form tevr-form" novalidate>' +
				'<div class="tec-sf-field" data-field="name"><label>Your Name</label><input type="text" name="name" required><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-field" data-field="email"><label>Email you RSVP\'d with</label><input type="email" name="email" required><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-field" data-field="rating"><label>Rating</label><select name="rating">' +
					'<option value="5">★★★★★</option><option value="4">★★★★☆</option><option value="3">★★★☆☆</option><option value="2">★★☆☆☆</option><option value="1">★☆☆☆☆</option>' +
				'</select></div>' +
				'<div class="tec-sf-field" data-field="comment"><label>Comment</label><textarea name="comment" rows="3" required></textarea><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-honeypot"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
				'<button type="submit" class="tec-sf-submit">Submit Review</button>' +
				'<div class="tec-sf-error-banner"></div>' +
				'<div class="tec-sf-success">Thanks — your review is awaiting a quick check before it goes live.</div>' +
			'</form>'
		);
	}

	function bind( root ) {
		var form = root.querySelector( '.tevr-form' );
		if ( ! form ) return;
		var errorBanner = root.querySelector( '.tec-sf-error-banner' );
		var success = root.querySelector( '.tec-sf-success' );

		form.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			errorBanner.classList.remove( 'visible' );

			var data = {
				event_id: EVENT_ID,
				name: val( form, 'name' ),
				email: val( form, 'email' ),
				rating: val( form, 'rating' ),
				comment: val( form, 'comment' ),
				website: val( form, 'website' ),
			};
			if ( ! data.name || ! data.email || ! data.comment ) {
				form.querySelectorAll( '.tec-sf-field' ).forEach( function ( el ) { el.classList.remove( 'invalid' ); } );
				if ( ! data.name ) invalid( form, 'name' );
				if ( ! data.email ) invalid( form, 'email' );
				if ( ! data.comment ) invalid( form, 'comment' );
				return;
			}

			fetch( REST + '/submit', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( result ) {
					if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Could not submit your review.' );
					form.style.display = 'none';
					success.classList.add( 'visible' );
				} )
				.catch( function ( err ) {
					errorBanner.textContent = err.message;
					errorBanner.classList.add( 'visible' );
				} );
		} );
	}

	function invalid( form, fieldName ) {
		var el = form.querySelector( '[data-field="' + fieldName + '"]' );
		if ( el ) el.classList.add( 'invalid' );
	}
	function val( form, name ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		return input ? input.value.trim() : '';
	}
	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}
})();
