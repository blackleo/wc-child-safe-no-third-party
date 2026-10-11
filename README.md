# WC Child-Safe Pages

Answers: *"how do you keep every outside script off a page a child could see?"*

Four independent layers (any one failing is caught by the next):

| Layer | What it does |
|---|---|
| Loader filters | Drops any enqueued `<script>`/`<link>` whose host is not your own domain |
| WordPress built-ins | Removes emoji script (loads from WP), oEmbed, wp-embed, generator tag, Gravatar |
| CSP header | `script-src 'self'`, `frame-src 'none'`, etc. — the browser refuses anything else |
| Output filter | Strips external `<script src>` / `<iframe>` from final HTML |

**Adult pages exception:** cart, checkout and account are excluded by default so a payment
gateway can load its own script. Change with the `wccs_is_child_page` filter.
Also: self-host fonts (no Google Fonts), no analytics, no pixels, no chat widgets.

## Install
Copy `wc-child-safe.php` to `wp-content/mu-plugins/`.

## Verify (run before launch and after every plugin update)
```bash
python tests/scan_external.py https://yoursite.com / /shop/ /product/sample-product/ /library/
```
Also open DevTools → Network on a child page and confirm every request is your domain, and Console shows no CSP violations.

## Filters
`wccs_allowed_hosts`, `wccs_csp`, `wccs_is_child_page`

## License
GPL-2.0-or-later
