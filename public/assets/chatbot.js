/**
 * WordPress AI Chatbot — Frontend widget logic.
 *
 * Pure vanilla JS — no jQuery, no external dependencies.
 */
( function () {
    'use strict';

    // ── UUID v4 (crypto API) ─────────────────────────────────────────────────
    function generateUUID() {
        return ( [1e7] + -1e3 + -4e3 + -8e3 + -1e11 ).replace( /[018]/g, function ( c ) {
            return ( c ^ crypto.getRandomValues( new Uint8Array(1) )[0] & 15 >> c / 4 ).toString(16);
        } );
    }

    // ── Cookie helpers ───────────────────────────────────────────────────────
    function getCookie( name ) {
        var match = document.cookie.match( new RegExp( '(?:^|; )' + name.replace( /([.*+?^=!:${}()|[\]/\\])/g, '\\$1' ) + '=([^;]*)' ) );
        return match ? decodeURIComponent( match[1] ) : '';
    }

    function setCookie( name, value, days ) {
        var expires = new Date( Date.now() + days * 864e5 ).toUTCString();
        var secure  = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent( value ) + '; expires=' + expires + '; path=/; SameSite=Lax' + secure;
    }

    // ── localStorage helpers (sauvegarde de l'identité) ──────────────────────
    var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

    function lsGet( key ) {
        try { return window.localStorage.getItem( key ) || ''; } catch ( e ) { return ''; }
    }
    function lsSet( key, value ) {
        try { window.localStorage.setItem( key, value ); } catch ( e ) {}
    }

    // Persiste l'identité dans le cookie ET le localStorage : elle survit ainsi à
    // l'effacement de l'un OU de l'autre (cas le plus courant de cookie « perdu »).
    function persistSession( key ) {
        setCookie( 'waicb_session', key, waicbConfig.cookieDays );
        lsSet( 'waicb_session', key );
    }

    // ── Session key (cookie prioritaire, localStorage en repli) ──────────────
    var sessionKey = getCookie( 'waicb_session' );
    if ( ! UUID_RE.test( sessionKey ) ) {
        // Cookie absent/invalide : on tente de restaurer depuis le localStorage
        // avant de créer une nouvelle identité (évite de recompter le visiteur).
        var stored = lsGet( 'waicb_session' );
        sessionKey = UUID_RE.test( stored ) ? stored : generateUUID();
    }
    persistSession( sessionKey );

    // ── Time formatter ───────────────────────────────────────────────────────
    function formatTime( date ) {
        var h = date.getHours().toString().padStart( 2, '0' );
        var m = date.getMinutes().toString().padStart( 2, '0' );
        return h + ':' + m;
    }

    // ── Markdown mini-renderer ───────────────────────────────────────────────
    function renderMarkdown( text ) {
        // 1. Extract safe iframes (Google Maps only) before HTML escaping.
        var iframes = [];
        var processed = text.replace(
            /<iframe[^>]+src=["']([^"']*)["'][^>]*>[\s\S]*?<\/iframe>/gi,
            function ( match, src ) {
                if ( /^https?:\/\/([a-z0-9-]+\.)*google\.com\/maps/i.test( src ) ) {
                    // Rebuild a clean iframe with ONLY the validated src — never
                    // re-insert the original tag (it could carry onload= etc.).
                    var safeSrc = src.replace( /"/g, '%22' );
                    iframes.push(
                        '<iframe src="' + safeSrc + '" style="border:0;width:100%;height:100%;" ' +
                        'loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>'
                    );
                    return '\x00MAP' + ( iframes.length - 1 ) + '\x00';
                }
                return ''; // Drop non-trusted iframes.
            }
        );

        // 2. Escape HTML.
        var escaped = processed
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );

        // Neutralise quotes/espaces dans une URL pour qu'elle ne puisse pas
        // s'échapper de l'attribut href (la passe d'échappement HTML ne gère que < > &).
        function safeHref( url ) {
            return url.replace( /"/g, '%22' ).replace( /'/g, '%27' ).replace( /\s/g, '%20' );
        }

        // 3. Markdown links [text](url).
        escaped = escaped.replace(
            /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
            function ( _, linkText, url ) {
                return '<a href="' + safeHref( url ) + '" target="_blank" rel="noopener noreferrer">' + linkText + '</a>';
            }
        );

        // 3a. Auto-lien des URLs en clair + protection des liens déjà construits.
        // On met de côté chaque <a> (placeholder) pour que les passes suivantes
        // (téléphone, gras, etc.) ne touchent jamais à son contenu, href compris.
        var anchors = [];
        function stashAnchor( html ) {
            anchors.push( html );
            return '\x00A' + ( anchors.length - 1 ) + '\x00';
        }
        // Protéger les liens Markdown déjà transformés.
        escaped = escaped.replace( /<a\b[^>]*>[\s\S]*?<\/a>/gi, stashAnchor );
        // Linkifier les URLs nues restantes (https://… ou www.…), schéma non ambigu.
        escaped = escaped.replace(
            /(?<=^|[\s(])((?:https?:\/\/|www\.)[^\s<]+?)([.,;:!?)]*)(?=\s|$|<)/gi,
            function ( _, url, trail ) {
                var href = /^https?:\/\//i.test( url ) ? url : 'http://' + url;
                return stashAnchor( '<a href="' + safeHref( href ) + '" target="_blank" rel="noopener noreferrer">' + url + '</a>' ) + trail;
            }
        );

        // 3b. Phone numbers → clickable tel: links.
        // Matches international (+221..., +33...) and local formats with spaces/dashes/dots.
        escaped = escaped.replace(
            /(?<!\d)(\+|00)?(\d[\d\s.\-()]{6,20}\d)(?!\d)/g,
            function ( match ) {
                // Count digits only — must have at least 7.
                var digits = match.replace( /\D/g, '' );
                if ( digits.length < 7 ) { return match; }
                var tel = ( match.trim().charAt(0) === '+' ? '+' : '' ) + digits;
                return '<a href="tel:' + tel + '" class="waicb-phone">' + match + '</a>';
            }
        );

        // 3c. Headings (### ## #) — converted before bold/italic.
        escaped = escaped.replace( /^(#{1,3})\s+(.+)$/gm, function ( _, hashes, content ) {
            var level = hashes.length;
            return '<h' + level + ' class="waicb-h' + level + '">' + content + '</h' + level + '>';
        } );

        // 4. Inline code (before bold/italic to avoid conflicts).
        escaped = escaped.replace( /`([^`]+)`/g, '<code>$1</code>' );

        // 5. Bold.
        escaped = escaped.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );

        // 6. Italic (single asterisk, not part of list).
        escaped = escaped.replace( /(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>' );

        // 7. Unordered lists: lines starting with "- ".
        escaped = escaped.replace( /((?:^|\n)- .+)+/g, function ( block ) {
            var items = block.split( '\n' ).filter( function ( l ) { return l.trim().indexOf( '- ' ) === 0; } );
            var lis   = items.map( function ( l ) { return '<li>' + l.replace( /^- /, '' ) + '</li>'; } ).join( '' );
            return '<ul>' + lis + '</ul>';
        } );

        // 8. Line breaks.
        escaped = escaped.replace( /\n/g, '<br>' );

        // 8b. Remove <br> immediately after/before block elements (ul, h1-h3).
        escaped = escaped.replace( /(<\/(?:ul|h[1-3])>)(<br>)+/g, '$1' );
        escaped = escaped.replace( /(<br>)+(<(?:ul|h[1-3])[\s>])/g, '$2' );

        // 9. Restore safe iframes inside a responsive container.
        escaped = escaped.replace( /\x00MAP(\d+)\x00/g, function ( _, idx ) {
            return '<div class="waicb-map-embed">' + iframes[ parseInt( idx, 10 ) ] + '</div>';
        } );

        // 10. Restaurer les liens mis de côté à l'étape 3a.
        escaped = escaped.replace( /\x00A(\d+)\x00/g, function ( _, idx ) {
            return anchors[ parseInt( idx, 10 ) ];
        } );

        return escaped;
    }

    // ── DOM references ───────────────────────────────────────────────────────
    var bubble       = document.getElementById( 'waicb-bubble' );
    var panel        = document.getElementById( 'waicb-panel' );
    var closeBtn     = panel ? panel.querySelector( '.waicb-panel__close' ) : null;
    var messages     = document.getElementById( 'waicb-messages' );
    var input        = document.getElementById( 'waicb-input' );
    var sendBtn      = document.getElementById( 'waicb-send' );
    var clearBtn     = document.getElementById( 'waicb-clear-input' );
    var charCounter  = document.getElementById( 'waicb-char-counter' );
    var scrollBtn    = document.getElementById( 'waicb-scroll-btn' );
    var quickReplies = document.getElementById( 'waicb-quick-replies' );

    if ( ! panel || ! messages || ! input || ! sendBtn ) {
        return; // Widget HTML not present.
    }

    // ── State ────────────────────────────────────────────────────────────────
    var isOpen           = panel.dataset.mode === 'shortcode';
    var welcomeShown     = false;
    var lastUserMsgEl    = null; // For tick update.
    var conversationStarted = false;

    // ── Scroll helpers ───────────────────────────────────────────────────────
    function isNearBottom() {
        return messages.scrollHeight - messages.scrollTop - messages.clientHeight < 80;
    }

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    if ( scrollBtn ) {
        messages.addEventListener( 'scroll', function () {
            if ( isNearBottom() ) {
                scrollBtn.classList.remove( 'waicb-scroll-btn--visible' );
            } else {
                scrollBtn.classList.add( 'waicb-scroll-btn--visible' );
            }
        } );

        scrollBtn.addEventListener( 'click', function () {
            scrollToBottom();
        } );
    }

    // ── Panel open / close ───────────────────────────────────────────────────
    function openPanel() {
        isOpen = true;
        panel.classList.add( 'waicb-panel--open' );
        panel.setAttribute( 'aria-modal', 'true' );
        input.focus();

        if ( ! welcomeShown && waicbConfig.welcomeMessage ) {
            welcomeShown = true;
            appendMessage( 'assistant', waicbConfig.welcomeMessage );
            renderQuickReplies();
        }
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove( 'waicb-panel--open' );
        panel.setAttribute( 'aria-modal', 'false' );
    }

    if ( bubble ) {
        bubble.addEventListener( 'click', function () {
            if ( isOpen ) { closePanel(); } else { openPanel(); }
        } );

        bubble.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' || e.key === ' ' ) {
                e.preventDefault();
                if ( isOpen ) { closePanel(); } else { openPanel(); }
            }
        } );
    }

    if ( closeBtn ) {
        closeBtn.addEventListener( 'click', closePanel );
    }

    // If shortcode mode, show welcome immediately.
    if ( panel.dataset.mode === 'shortcode' && waicbConfig.welcomeMessage ) {
        welcomeShown = true;
        appendMessage( 'assistant', waicbConfig.welcomeMessage );
        renderQuickReplies();
    }

    // ── Avatar helper ────────────────────────────────────────────────────────
    var botSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M9 11V8a3 3 0 0 1 6 0v3"/><circle cx="9" cy="16" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="16" r="1" fill="currentColor" stroke="none"/></svg>';

    function buildAvatar( extraClass ) {
        var el = document.createElement( 'div' );
        el.className = 'waicb-msg__avatar' + ( extraClass ? ' ' + extraClass : '' );
        el.setAttribute( 'aria-hidden', 'true' );
        if ( waicbConfig.bubbleIcon ) {
            var img = document.createElement( 'img' );
            img.src    = waicbConfig.bubbleIcon;
            img.alt    = '';
            img.width  = 28;
            img.height = 28;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
            el.appendChild( img );
        } else {
            el.innerHTML = botSvg;
        }
        return el;
    }

    // ── Append a message bubble ──────────────────────────────────────────────
    function appendMessage( role, content ) {
        var wrapper = document.createElement( 'div' );
        wrapper.className = 'waicb-msg waicb-msg--' + role;

        // Avatar (assistant only).
        if ( role === 'assistant' ) {
            wrapper.appendChild( buildAvatar( null ) );
        }

        // Body.
        var body = document.createElement( 'div' );
        body.className = 'waicb-msg__body';

        var contentEl = document.createElement( 'div' );
        contentEl.className = 'waicb-msg__content';
        contentEl.innerHTML = renderMarkdown( content );
        body.appendChild( contentEl );

        // Timestamp + tick row.
        var meta = document.createElement( 'div' );
        meta.className = 'waicb-msg__meta';

        var timeEl = document.createElement( 'span' );
        timeEl.className   = 'waicb-msg__time';
        timeEl.textContent = formatTime( new Date() );
        meta.appendChild( timeEl );

        if ( role === 'user' ) {
            var tick = document.createElement( 'span' );
            tick.className   = 'waicb-tick waicb-tick--sent';
            tick.setAttribute( 'aria-label', 'Envoyé' );
            tick.textContent = '✓';
            meta.appendChild( tick );
        }

        body.appendChild( meta );
        wrapper.appendChild( body );

        messages.appendChild( wrapper );

        // Scroll only if near bottom.
        if ( isNearBottom() ) {
            scrollToBottom();
        }

        return wrapper;
    }

    // ── Typing indicator ─────────────────────────────────────────────────────
    function showTyping() {
        var el = document.createElement( 'div' );
        el.className = 'waicb-typing';
        el.id        = 'waicb-typing';

        el.appendChild( buildAvatar( 'waicb-msg__avatar--typing' ) );

        var dots = document.createElement( 'div' );
        dots.className = 'waicb-typing__dots';
        dots.innerHTML = '<span></span><span></span><span></span>';
        el.appendChild( dots );

        messages.appendChild( el );
        if ( isNearBottom() ) { scrollToBottom(); }
    }

    function hideTyping() {
        var el = document.getElementById( 'waicb-typing' );
        if ( el ) { el.parentNode.removeChild( el ); }
    }

    // ── Quick replies ────────────────────────────────────────────────────────
    function renderQuickReplies() {
        if ( ! quickReplies ) { return; }
        var suggestions = waicbConfig.quickReplies;
        if ( ! suggestions || ! suggestions.length ) { return; }

        quickReplies.innerHTML = '';
        suggestions.forEach( function ( text ) {
            var btn = document.createElement( 'button' );
            btn.type      = 'button';
            btn.className = 'waicb-quick-reply';
            btn.textContent = text;
            btn.addEventListener( 'click', function () {
                hideQuickReplies();
                input.value = text;
                sendMessage();
            } );
            quickReplies.appendChild( btn );
        } );
        quickReplies.classList.add( 'waicb-quick-replies--visible' );
    }

    function hideQuickReplies() {
        if ( quickReplies ) {
            quickReplies.classList.remove( 'waicb-quick-replies--visible' );
        }
    }

    // ── Char counter & clear button ──────────────────────────────────────────
    function updateInputMeta() {
        var len = input.value.length;

        if ( charCounter ) {
            charCounter.textContent = len + ' / 4000';
            charCounter.classList.toggle( 'waicb-char-counter--warn', len > 3800 );
        }

        if ( clearBtn ) {
            clearBtn.classList.toggle( 'waicb-clear-input--visible', len > 0 );
        }
    }

    if ( clearBtn ) {
        clearBtn.addEventListener( 'click', function () {
            input.value = '';
            input.style.height = '';
            updateInputMeta();
            input.focus();
        } );
    }

    // ── Send a message ───────────────────────────────────────────────────────
    function sendMessage() {
        var text = input.value.trim();
        if ( ! text ) { return; }

        input.value        = '';
        input.style.height = '';
        sendBtn.disabled   = true;
        updateInputMeta();

        // Hide quick replies on first send.
        if ( ! conversationStarted ) {
            conversationStarted = true;
            hideQuickReplies();
        }

        lastUserMsgEl = appendMessage( 'user', text );
        showTyping();

        fetch( waicbConfig.restUrl, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {
                'Content-Type': 'application/json',
                // Authenticates the cookie session so the REST request runs as the
                // same user that generated the chat nonce (fixes "Nonce invalide"
                // for logged-in users).
                'X-WP-Nonce': waicbConfig.restNonce,
            },
            body: JSON.stringify( {
                message:     text,
                session_key: sessionKey,
                nonce:       waicbConfig.nonce,
            } ),
        } )
        .then( function ( res ) { return res.json(); } )
        .then( function ( data ) {
            hideTyping();
            sendBtn.disabled = false;

            // Upgrade tick to "delivered".
            if ( lastUserMsgEl ) {
                var tick = lastUserMsgEl.querySelector( '.waicb-tick' );
                if ( tick ) {
                    tick.className   = 'waicb-tick waicb-tick--delivered';
                    tick.textContent = '✓✓';
                    tick.setAttribute( 'aria-label', 'Lu' );
                }
            }

            if ( data.success && data.data && data.data.reply ) {
                appendMessage( 'assistant', data.data.reply );
                if ( data.data.session_key ) {
                    sessionKey = data.data.session_key;
                    persistSession( sessionKey );
                }
            } else {
                var errMsg = ( data.data && data.data.message )
                    ? data.data.message
                    : waicbConfig.i18n.errorMessage;
                appendMessage( 'error', errMsg );
            }
        } )
        .catch( function () {
            hideTyping();
            sendBtn.disabled = false;
            appendMessage( 'error', waicbConfig.i18n.errorMessage );
        } );
    }

    sendBtn.addEventListener( 'click', sendMessage );

    input.addEventListener( 'keydown', function ( e ) {
        if ( e.key === 'Enter' && ! e.shiftKey ) {
            e.preventDefault();
            sendMessage();
        }
    } );

    // Auto-grow textarea + meta update.
    input.addEventListener( 'input', function () {
        this.style.height = '';
        this.style.height = Math.min( this.scrollHeight, 120 ) + 'px';
        updateInputMeta();
    } );

}() );
