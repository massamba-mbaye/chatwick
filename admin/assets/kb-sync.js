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
					var msg = json && json.data && json.data.message ? json.data.message : 'Erreur.';
					status.textContent = 'Erreur : ' + msg;
					btn.disabled = false;
					return;
				}
				var d = json.data;
				addResults( d.results );
				var t   = d.total || 0;
				var pct = t > 0 ? Math.min( 100, Math.round( ( d.processed / t ) * 100 ) ) : 100;
				bar.style.width = pct + '%';
				status.textContent = ( t > 0 ? ( d.processed + ' / ' + t + ' contenus' ) : 'Terminé' ) +
					' · ' + d.chunks_total + ' passages indexés';

				if ( d.done ) {
					btn.disabled = false;
					btn.textContent = 'Synchroniser à nouveau';
					summary.style.display = '';
					summary.innerHTML = '<strong>Terminé.</strong> ' +
						'Indexés : ' + totals.indexed + ' · Inchangés : ' + totals.unchanged +
						' · Ignorés : ' + ( totals.skipped + totals.skipped_quota ) +
						( totals.error ? ' · Erreurs : ' + totals.error : '' ) + '.';
				} else {
					step( page + 1, t );
				}
			} )
			.catch( function () {
				status.textContent = 'Erreur réseau.';
				btn.disabled = false;
			} );
	}

	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		box.style.display = '';
		summary.style.display = 'none';
		bar.style.width = '0';
		status.textContent = 'Démarrage…';
		Object.keys( totals ).forEach( function ( k ) { totals[ k ] = 0; } );
		step( 1, 0 );
	} );
} )();
