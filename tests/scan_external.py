#!/usr/bin/env python3
"""Scan pages for any resource loaded from a host other than the site's own.

Usage: python scan_external.py https://example.com / /shop/ /product/sample/ ...
Exit code 1 if anything external is found (use as a launch gate / CI check).
Standard library only.
"""
import sys
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen

ATTRS = {"script": "src", "link": "href", "iframe": "src", "img": "src",
         "source": "src", "video": "src", "audio": "src", "embed": "src", "object": "data"}
LINK_RELS = {"stylesheet", "preload", "icon", "prefetch", "preconnect", "dns-prefetch"}


class Finder(HTMLParser):
    def __init__(self, base, own):
        super().__init__()
        self.base, self.own, self.hits = base, own, []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        key = ATTRS.get(tag)
        if not key or not a.get(key):
            return
        if tag == "link" and a.get("rel") not in LINK_RELS:
            return
        url = urljoin(self.base, a[key])
        host = urlparse(url).netloc
        if host and host != self.own:
            self.hits.append((tag, url))


def main():
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    site = sys.argv[1].rstrip("/")
    own = urlparse(site).netloc
    bad = 0
    for path in sys.argv[2:]:
        url = site + path
        req = Request(url, headers={"User-Agent": "child-safe-scan"})
        html = urlopen(req).read().decode("utf-8", "replace")
        f = Finder(url, own)
        f.feed(html)
        if f.hits:
            bad += len(f.hits)
            print(f"FAIL {url}")
            for tag, u in f.hits:
                print(f"   <{tag}> {u}")
        else:
            print(f"ok   {url}")
    sys.exit(1 if bad else 0)


main()
