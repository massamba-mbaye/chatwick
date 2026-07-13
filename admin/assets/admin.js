/**
 * AI Chat Assistant — Admin JS.
 *
 * - Tabs (Assistant / Apparence / Affichage / Avancé)
 * - Guided onboarding helpers (test connection, step 3 link, char counter)
 * - Bubble icon media uploader
 * - Display rules show/hide
 * - "Clear conversations" confirmation
 */
( function () {
    'use strict';

    // ── Tabs ──────────────────────────────────────────────────────────────────
    function activateTab( name ) {
        document.querySelectorAll( '.waicb-tab' ).forEach( function ( t ) {
            t.classList.toggle( 'active', t.getAttribute( 'data-tab' ) === name );
        } );
        document.querySelectorAll( '.waicb-pane' ).forEach( function ( p ) {
            p.classList.toggle( 'active', p.getAttribute( 'data-pane' ) === name );
        } );
    }

    document.querySelectorAll( '.waicb-tab' ).forEach( function ( t ) {
        t.addEventListener( 'click', function () { activateTab( t.getAttribute( 'data-tab' ) ); } );
    } );

    // Step 3 → open the Assistant tab and focus the instructions.
    var goAssistant = document.getElementById( 'waicb-go-assistant' );
    if ( goAssistant ) {
        goAssistant.addEventListener( 'click', function () {
            activateTab( 'assistant' );
            var f = document.getElementById( 'waicb_instructions' );
            if ( f ) {
                f.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                setTimeout( function () { f.focus(); }, 250 );
            }
        } );
    }

    // ── Instructions char counter ─────────────────────────────────────────────
    var instr = document.getElementById( 'waicb_instructions' );
    var instrCount = document.getElementById( 'waicb-instr-count' );
    if ( instr && instrCount ) {
        var updateCount = function () { instrCount.textContent = instr.value.length; };
        instr.addEventListener( 'input', updateCount );
        updateCount();
    }

    // ── Credit balance (Cloud) ────────────────────────────────────────────────
    var creditsEl = document.getElementById( 'waicb-credits' );
    if ( creditsEl ) {
        var creditsData = new FormData();
        creditsData.append( 'action', 'waicb_credits' );
        creditsData.append( 'nonce', waicbAdmin.nonce );

        fetch( waicbAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: creditsData } )
        .then( function ( r ) { return r.json(); } )
        .then( function ( d ) {
            if ( d.success ) {
                creditsEl.textContent = Number( d.data.credits ).toLocaleString( 'fr-FR' ) + ' ' + waicbAdmin.i18n.creditsSuffix;
            } else {
                creditsEl.textContent = waicbAdmin.i18n.creditsUnavailable;
            }
        } )
        .catch( function () { creditsEl.textContent = waicbAdmin.i18n.creditsUnavailable; } );
    }

    // ── Mark an onboarding step as done ───────────────────────────────────────
    function markStepDone( id ) {
        var el = document.getElementById( id );
        if ( ! el ) { return; }
        el.classList.remove( 'is-active' );
        el.classList.add( 'is-done' );
        var mark = el.querySelector( '.waicb-step__mark' );
        if ( mark ) { mark.textContent = '✓'; }
    }

    // ── Test Cloud connection ─────────────────────────────────────────────────
    var testBtn    = document.getElementById( 'waicb-test-cloud' );
    var testResult = document.getElementById( 'waicb-test-cloud-result' );

    if ( testBtn && testResult ) {
        testBtn.addEventListener( 'click', function () {
            testResult.textContent = waicbAdmin.i18n.testing;
            testResult.className    = '';
            testBtn.disabled        = true;

            var formData = new FormData();
            formData.append( 'action', 'waicb_test_api' );
            formData.append( 'nonce', waicbAdmin.nonce );
            formData.append( 'provider', 'cloud' );

            var keyField = document.getElementById( 'waicb_cloud_key' );
            var keyValue = keyField ? keyField.value : '';
            if ( keyValue && keyValue.indexOf( '•' ) === -1 && keyValue.indexOf( '****' ) === -1 ) {
                formData.append( 'api_key', keyValue );
            }

            fetch( waicbAdmin.ajaxUrl, {
                method:      'POST',
                credentials: 'same-origin',
                body:        formData,
            } )
            .then( function ( res ) { return res.json(); } )
            .then( function ( data ) {
                testBtn.disabled = false;
                if ( data.success ) {
                    testResult.textContent = data.data.message;
                    testResult.className   = 'success';
                    // Immediate visual feedback (saved state still applies on submit).
                    markStepDone( 'waicb-s1' );
                    markStepDone( 'waicb-s2' );
                    var status = document.getElementById( 'waicb-status' );
                    var ico    = document.getElementById( 'waicb-status-ico' );
                    var text   = document.getElementById( 'waicb-status-text' );
                    if ( status && ! status.classList.contains( 'waicb-status--live' ) ) {
                        status.className = 'waicb-status waicb-status--connected';
                        if ( ico ) { ico.textContent = '✓'; }
                        if ( text ) { text.innerHTML = '<strong>' + waicbAdmin.i18n.connected + '</strong> ' + waicbAdmin.i18n.enableHint; }
                    }
                } else {
                    testResult.textContent = '✗ ' + data.data.message;
                    testResult.className   = 'error';
                }
            } )
            .catch( function ( err ) {
                testBtn.disabled       = false;
                testResult.textContent = '✗ ' + err.message;
                testResult.className   = 'error';
            } );
        } );
    }

    // ── Bubble icon media uploader ───────────────────────────────────────────
    var uploadBtn   = document.getElementById( 'waicb-upload-icon' );
    var removeBtn    = document.getElementById( 'waicb-remove-icon' );
    var iconInput    = document.getElementById( 'waicb_bubble_icon' );
    var iconPreview  = document.getElementById( 'waicb-icon-preview' );

    if ( uploadBtn && typeof wp !== 'undefined' && wp.media ) {
        var mediaFrame;

        uploadBtn.addEventListener( 'click', function ( e ) {
            e.preventDefault();

            if ( mediaFrame ) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media( {
                title:    'Choisir l\'icône de la bulle',
                button:   { text: 'Utiliser cette image' },
                multiple: false,
                library:  { type: 'image' },
            } );

            mediaFrame.on( 'select', function () {
                var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
                var url = attachment.url;

                iconInput.value = url;
                iconPreview.querySelector( 'img' ).src = url;
                iconPreview.style.display = '';
                removeBtn.style.display   = '';
            } );

            mediaFrame.open();
        } );

        if ( removeBtn ) {
            removeBtn.addEventListener( 'click', function () {
                iconInput.value           = '';
                iconPreview.style.display = 'none';
                removeBtn.style.display   = 'none';
            } );
        }
    }

    // ── Display rules: show/hide page list ───────────────────────────────────
    var displayRadios   = document.querySelectorAll( 'input[name="waicb_display_mode"]' );
    var displayPagesRow = document.getElementById( 'waicb-display-pages-row' );

    if ( displayRadios.length && displayPagesRow ) {
        displayRadios.forEach( function ( radio ) {
            radio.addEventListener( 'change', function () {
                displayPagesRow.style.display = this.value === 'all' ? 'none' : '';
            } );
        } );
    }

    // ── Clear conversations confirmation ─────────────────────────────────────
    var clearForm = document.getElementById( 'waicb-clear-form' );

    if ( clearForm ) {
        clearForm.addEventListener( 'submit', function ( e ) {
            if ( ! window.confirm( waicbAdmin.i18n.confirmClear ) ) {
                e.preventDefault();
            }
        } );
    }

}() );
