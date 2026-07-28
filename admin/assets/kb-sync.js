/**
 * Synchronisation de la base de connaissances (page admin « Base de connaissances »).
 * Pilote la progression par lots via AJAX. Config passée par wp_localize_script (waicbKb).
 */
( function () {
	var btn = document.getElementById( 'waicb-kb-sync' );
	if ( ! btn ) { return; }

	var cfg     = window.waicbKb || {};
	var ajax    = cfg.ajaxUrl || '';
	var nonce   = cfg.nonce || '';
	var t       = cfg.i18n || {};

	// Mini-sprintf : gère %s / %d et les positions %1$d, %2$d (comme côté PHP).
	function fmt( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i    = 0;
		return String( tpl || '' )
			.replace( /%(\d+)\$[ds]/g, function ( _, n ) { return args[ n - 1 ]; } )
			.replace( /%[ds]/g, function () { return args[ i++ ]; } );
	}
	var box     = document.getElementById( 'waicb-kb-progress' );
	var bar     = document.getElementById( 'waicb-kb-bar' );
	var status  = document.getElementById( 'waicb-kb-status' );
	var summary = document.getElementById( 'waicb-kb-summary' );

	var totals = { indexed: 0, unchanged: 0, deleted: 0, skipped: 0, skipped_quota: 0, error: 0 };

	function addResults( r ) {
		if ( ! r ) { return; }
		Object.keys( totals ).forEach( function ( k ) { totals[ k ] += ( r[ k ] || 0 ); } );
	}

	function step( page, total ) {
		var data = new FormData();
		data.append( 'action', 'waicb_kb_sync' );
		data.append( 'nonce', nonce );
		data.append( 'page', page );
		data.append( 'total', total );

		fetch( ajax, { method: 'POST', credentials: 'same-origin', body: data } )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var msg = json && json.data && json.data.message ? json.data.message : t.error;
					status.textContent = fmt( t.errorWith, msg );
					btn.disabled = false;
					return;
				}
				var d     = json.data;
				addResults( d.results );
				var tot = d.total || 0;
				var pct = tot > 0 ? Math.min( 100, Math.round( ( d.processed / tot ) * 100 ) ) : 100;
				bar.style.width = pct + '%';
				status.textContent = ( tot > 0 ? fmt( t.progress, d.processed, tot ) : t.done ) +
					' · ' + fmt( t.chunks, d.chunks_total );

				if ( d.done ) {
					btn.disabled = false;
					btn.textContent = t.resync;
					summary.style.display = '';
					summary.innerHTML = '<strong>' + t.summaryDone + '</strong> ' +
						t.labelIndexed + ' : ' + totals.indexed + ' · ' + t.labelUnchanged + ' : ' + totals.unchanged +
						' · ' + t.labelSkipped + ' : ' + ( totals.skipped + totals.skipped_quota ) +
						( totals.error ? ' · ' + t.labelErrors + ' : ' + totals.error : '' ) + '.';
				} else {
					step( page + 1, tot );
				}
			} )
			.catch( function () {
				status.textContent = t.networkError;
				btn.disabled = false;
			} );
	}

	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		box.style.display = '';
		summary.style.display = 'none';
		bar.style.width = '0';
		status.textContent = t.starting;
		Object.keys( totals ).forEach( function ( k ) { totals[ k ] = 0; } );
		step( 1, 0 );
	} );
} )();
