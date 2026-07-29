=== Chatwick ===
Contributors:      massambambaye
Tags:              chatbot, ai, assistant, chatwick, support
Requires at least: 6.0
Tested up to:      7.0
Stable tag:        1.9.1
Requires PHP:      7.4
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Add an AI chatbot to any WordPress site, powered by the Chatwick Cloud service, with no AI API key to manage.

== Description ==

Chatwick adds an AI chatbot to your WordPress site, powered by the **Chatwick** Cloud service. You don't manage any OpenAI or Anthropic API key: create a Chatwick account, buy prepaid credits (1 credit = 1 conversation), paste your account key, and go live in minutes.

How it works: the plugin sends each message to the Chatwick service, which holds the AI keys server-side, checks your credit balance, calls the AI model, and returns the answer. You only ever paste an **account key**.

The optional **knowledge base** feature lets the assistant answer from your own site content (pages, posts, WooCommerce products and any public custom post type): you choose what to index, the plugin sends it to the Chatwick service for indexing, and content stays in sync automatically as you publish, update or delete it.

This plugin requires an account on the third-party Chatwick service to function. See the "External services" section below for exactly what data is sent and links to the terms and privacy policy.

= Main Features =

* Powered by the Chatwick Cloud service, with no OpenAI/Anthropic key to handle
* Prepaid credits (1 credit = 1 conversation), topped up from the Chatwick dashboard
* **Knowledge base (RAG):** the assistant answers from your own pages, posts and WooCommerce products, with automatic background sync
* Per-site assistant instructions (persona/tone) sent securely to the service
* Floating chat bubble with configurable position, color, and title
* `[waicb_chatbot]` shortcode for inline embedding anywhere
* Per-visitor conversation history tracked via UUID cookie
* Encryption at rest for the account key
* Admin dashboard: settings, conversation list, message logs
* Rate limiting (20 requests/minute per IP) via WordPress transients
* Multisite compatible
* Zero Composer dependencies: native PHP and WordPress Core only

== Installation ==

1. Upload the `chatwick` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin from the **Plugins** menu in WordPress.
3. Create an account and get your key at https://chatwick.app/dashboard.php
4. Go to **Chatwick > Settings**, paste your account key, write your assistant instructions, and enable the chatbot.

== Frequently Asked Questions ==

= Do I need an OpenAI or Anthropic API key? =

No. The Chatwick service holds the AI keys for you. You only create a Chatwick account, buy credits, and paste your account key into the plugin.

= How is it billed? =

Prepaid credits: 1 credit = 1 visitor conversation (all messages in the conversation included). You top up from the Chatwick dashboard (Wave, Orange Money, Free Money, or card). When credits run out, the chatbot asks you to recharge.

= Does the shortcode work with Elementor, Divi, or Gutenberg? =

Yes. Use `[waicb_chatbot]` in any text block or shortcode widget.

= Is the account key stored securely? =

Yes. The Chatwick account key is encrypted in the database using AES-256-CBC with a key derived from the WordPress `AUTH_KEY` constant.

= My site is behind Cloudflare or a load balancer: how is the rate limit counted? =

By default the rate limit uses the direct connection IP (`REMOTE_ADDR`), which cannot be spoofed. If your site sits behind a trusted reverse proxy / CDN, return true on the `waicb_trust_proxy_headers` filter to honor forwarded-for headers instead.

= How do I temporarily disable the chatbot? =

In **Chatwick > Settings**, uncheck "Enable chatbot". The widget disappears immediately without deactivating the plugin.

== External services ==

This plugin connects to the **Chatwick** Cloud service (https://chatwick.app), operated by Massamba MBAYE, to power the chatbot. An account on this service is required for the plugin to function. The service is what holds the AI provider keys and runs the AI models on your behalf.

The plugin communicates with the service in the following cases:

* **When a visitor sends a chat message** (endpoint: `https://api.chatwick.app/api/chat.php`). The plugin sends: the visitor's message and recent conversation messages, your assistant instructions, your account key, the site URL, a per-visitor conversation identifier, and a salted **hashed** IP address of the visitor (the raw IP is not sent). This is used to generate the reply and to bill per conversation.
* **When checking your balance / connection** (endpoint: `https://api.chatwick.app/api/status.php`). The plugin sends your account key and site URL.
* **When you use the optional Knowledge base feature** (endpoint: `https://api.chatwick.app/api/kb-sync.php`). The plugin sends the content you chose to index (titles, text, excerpts, URLs, and for WooCommerce products their price and attributes), plus your account key and site URL. Content is re-sent automatically when you publish, update or delete it. You control which content types are indexed, and can index none.

The Chatwick service in turn relies on AI providers (OpenAI, Anthropic) and other sub-processors to deliver the service. What data is stored, for how long, and the full list of sub-processors are described in the service's policies.

By installing this plugin and connecting your account key, you agree to the Chatwick service terms and privacy policy:

* Terms of Service: https://chatwick.app/cgu.php
* Privacy Policy: https://chatwick.app/confidentialite.php

== Screenshots ==

1. The assistant answering a real visitor question on a live site, in a floating bubble matched to the site's colors.
2. Knowledge base: choose which content types to index, sync them to Chatwick, and let auto sync keep everything up to date.
3. Guided setup: create your Chatwick account, paste your account key, test the connection, then switch the chatbot on.
4. Assistant tab: define your bot's persona and its welcome message. These instructions are sent with every message.
5. Appearance tab: custom bubble icon, widget title, main color and screen position.
6. Advanced tab: clickable question suggestions shown at the start of a chat, and conversation cookie duration.

== Changelog ==

= 1.9.1 =
* Improve: the assistant now introduces itself with your site's name when no custom persona is set, instead of a generic message.
* Improve: full internationalization of the admin interface. The knowledge base sync screen and the media picker labels now go through the translation system, and the translation template (.pot) has been refreshed.

= 1.9.0 =
* New: the Conversations screen now shows a summary bar with your remaining credits, the site's monthly quota (used / limit and percentage) and the estimated balance autonomy in days.
* New: the settings screen now shows your site's monthly quota (used / limit and percentage, or "unlimited") next to your credit balance.
* New: a reset button in the chat header lets visitors start a fresh conversation at any time.
* New: the saved conversation now clears automatically after 24h of inactivity, in line with the server's conversation window.
* Improvement: the chat now always scrolls to the latest message when you send one and when the assistant replies.
* Housekeeping: removed the obsolete "API Logs" screen and its local token log (usage and costs are tracked in your Chatwick account); the unused local table is dropped on update.

= 1.8.0 =
* New: the conversation now stays visible when the visitor switches tabs or reloads the page, until the session expires (the transcript is restored on load).
* New: "Bubble effect" appearance setting. Make the floating bubble more visible with a soft shadow, a colored glow, or a glow with pulse. The effect uses your main color automatically. Respects reduced-motion preferences.

= 1.7.0 =
* New: Knowledge base (RAG). The assistant now answers from your own site content (pages, posts, WooCommerce products and any public custom post type). A new "Base de connaissances" screen lets you choose which content types to index and sync them to the Chatwick service.
* New: automatic sync. Published, updated, unpublished and deleted content is kept in sync in the background (no manual action needed after the first sync). Scheduled posts are indexed when they go live; removed content is dropped from the knowledge base.
* Note: requires the Chatwick service to be updated (knowledge base storage + retrieval, and an OpenAI key for embeddings) and RAG enabled for your account.

= 1.6.2 =
* New: bare links in chatbot replies are now clickable. URLs written in plain text (https://… or www.…) are auto-linked, in addition to Markdown links.
* Fix: links that contain a long digit sequence (for example WhatsApp wa.me links) are no longer broken by the phone-number detection. Built links are protected from the later rendering passes.

= 1.6.1 =
* Improve: the visitor identity (used to group a conversation) is now backed up in localStorage in addition to the cookie, so it survives the loss of either one and the visitor is not re-counted as a new conversation.
* Improve: the plugin sends the visitor's salted, hashed IP to the Chatwick service as an identity fallback. Visitors without a usable cookie are grouped per IP over the billing window instead of being billed per message.

= 1.6.0 =
* Change: billing is now per conversation (1 credit = 1 visitor conversation, all messages included) instead of per message. The plugin sends the visitor conversation key to the Chatwick service. Balance and wording now read "conversations".

= 1.5.0 =
* New: the settings status banner now shows the remaining Chatwick credit balance, with a quick "Recharger" link. Read-only, consumes no credit.

= 1.4.2 =
* Change: assistant instructions limit raised from 2000 to 2500 characters.

= 1.4.1 =
* Improve: the "View details" popup on the Plugins page is now richer (Description, Installation, FAQ, full changelog, and metadata), sourced from readme.txt instead of the raw GitHub release notes.

= 1.4.0 =
* New: redesigned settings page with a guided onboarding (status banner + 4 steps: create account, connect key, personalize, activate) and tabbed secondary settings (Assistant, Apparence, Affichage, Avancé).
* New: connection test now updates the status banner and step checkmarks instantly.
* Assistant instructions are now in a dedicated, always-accessible "Assistant" tab.
* All previous widget settings are kept (bubble icon, title, welcome message, color, position, cookie, quick replies, display rules).

= 1.3.1 =
* New: contextual Chatwick links in the settings: "Créer un compte" when no key is set, "Gérer mes crédits / Recharger" once connected (opens the Chatwick dashboard).

= 1.3.0 =
* Change: the plugin is now powered exclusively by the Chatwick Cloud service. The OpenAI and Claude "bring-your-own-key" providers have been removed. You only paste a Chatwick account key (no AI API key to manage).
* New: per-site "Assistant instructions" field (persona/tone) sent to the service with each message.
* Simplified settings: provider selector, AI keys, model/temperature/mode/Assistants and token settings removed.

= 1.2.0 =
* New: "Cloud" provider. Connect the chatbot to a prepaid credits SaaS (e.g. Chatwick) instead of your own OpenAI/Anthropic key. The site sends only an account key; the SaaS holds the AI keys, checks credits, and bills 1 credit per message.
* New: settings section for the Cloud provider (proxy URL + account key) with a connection test that validates the key without consuming a credit.

= 1.1.2 =
* Fix: database tables are now self-healing. They are (re)created automatically on update if missing, instead of only on activation. Fixes empty Conversations/Logs pages and lost chat history after an in-place update.
* Fix: "Trying to access array offset on null" warning on the Logs page when no logs exist yet.

= 1.1.1 =
* Fix: "Nonce invalide" error when sending a message while logged in. The chat request now authenticates the cookie session via the WordPress REST nonce (X-WP-Nonce).
* UX: the settings page now shows only the active provider's options (OpenAI or Claude), and the OpenAI engine reveals either Chat Completion or Assistants API fields. Shared generation settings (system prompt, max tokens, history) are grouped separately.

= 1.1.0 =
* New: Claude (Anthropic) provider via the Messages API, alongside OpenAI.
* New: provider selector with a separate, encrypted API key per provider.
* New: automatic updates from GitHub Releases.
* Security: rate limiting now uses the direct connection IP by default (ignores spoofable forwarded-for headers unless the `waicb_trust_proxy_headers` filter opts in).
* Security: hardened the frontend Markdown renderer against malformed links and iframes.
* Change: API logs now show model and token usage only (no estimated USD cost).
* Internal: code cleanup and de-duplication.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.9.1 =
The assistant now introduces itself with your site's name, and the admin interface is fully translatable with a refreshed translation template.

= 1.9.0 =
The settings screen now shows your site's monthly quota next to your balance, and the chat always scrolls to the latest message. The obsolete API Logs screen is removed and its unused local table is dropped on update.

= 1.7.0 =
The assistant can now answer from your own site content (knowledge base / RAG) with automatic background sync. Requires the Chatwick service to be updated and RAG enabled for your account.

= 1.6.2 =
Plain-text links are now clickable and WhatsApp (wa.me) links are no longer broken by phone-number detection.

= 1.6.1 =
More robust per-conversation billing: visitor identity is backed up in localStorage and falls back to a hashed IP, so visitors are grouped correctly instead of being re-counted.

= 1.6.0 =
Billing is now per conversation. Requires the Chatwick service to be updated (conversations table + per-conversation logic).

= 1.5.0 =
Shows your remaining credit balance in the settings. Requires the Chatwick service to be updated (status endpoint).

= 1.4.1 =
Richer plugin details popup on the Plugins page.

= 1.4.0 =
Redesigned, guided settings page. All your existing settings are preserved.

= 1.3.1 =
Adds quick links to create a Chatwick account or recharge credits from the settings page.

= 1.3.0 =
Major change: the chatbot now runs only through the Chatwick Cloud service. After updating, go to Chatwick > Settings and paste your Chatwick account key. OpenAI/Claude keys are no longer used.

= 1.1.2 =
Fixes missing Conversations/Logs tables after an update and a PHP warning on the Logs page.

= 1.1.1 =
Fixes the "Nonce invalide" error for logged-in users and streamlines the settings page per provider.

= 1.1.0 =
Adds Claude (Anthropic) support, automatic GitHub updates, and security hardening. Install once manually to enable future automatic updates.

= 1.0.0 =
First stable release.
