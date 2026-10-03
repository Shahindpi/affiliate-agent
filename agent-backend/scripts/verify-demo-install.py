"""Check a freshly seeded, locally running Dewdora API over HTTP."""

import json
import os
from urllib.error import HTTPError
from urllib.request import Request, urlopen

BASE = os.environ.get("DEWDORA_API_URL", "http://127.0.0.1:8000/api/v1").rstrip("/")


def request(path, payload=None, token=None):
    headers = {"Accept": "application/json"}
    if payload is not None:
        headers["Content-Type"] = "application/json"
    if token:
        headers["Authorization"] = f"Bearer {token}"
    req = Request(BASE + path, data=json.dumps(payload).encode() if payload is not None else None, headers=headers)
    try:
        with urlopen(req, timeout=20) as response:
            assert response.status == 200, (path, response.status)
            return json.load(response)["data"]
    except HTTPError as exc:
        raise AssertionError(f"{path}: HTTP {exc.code}: {exc.read().decode()[:400]}") from exc


login = request("/auth/login", {"email": "admin@example.com", "password": "Admin@1234567"})
assert login["token"] and login["user"]["role"]["slug"] == "admin"
assert login["user"]["status"] is True
token = login["token"]
homepage = request("/public/homepage")
assert len(homepage["carousel_products"]) == 12
assert len(homepage["latest_products"]) == 12
assert len(request("/public/products?per_page=3&page=5")) == 3
assert len(homepage["hero_banners"]) >= 1
assert homepage["statistics"]["products"] == 16
with urlopen(homepage["carousel_products"][0]["featured_image"], timeout=20) as image:
    assert image.status == 200 and image.headers.get_content_type() == "image/png"
for path in ("users", "roles", "posts", "affiliate-products", "categories", "tags", "brands", "affiliate-networks", "hero-banners", "media", "dashboard", "affiliate-analytics"):
    request("/admin/" + path, token=token)
for path in ("posts", "products", "categories", "tags", "brands", "affiliate-networks"):
    request("/public/" + path)
request("/public/products/northstar-writing-desk")
request("/public/posts/choosing-an-ai-writing-companion")
request("/public/categories/ai-tools")
request("/public/brands/northstar-labs")
print("Seeded admin login, public/admin API requests and image loading passed; homepage has 12 initial products and 16 total products.")
