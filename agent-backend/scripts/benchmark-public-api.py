#!/usr/bin/env python3
"""Measure Dewdora API latency and response size without mutating application data."""

import json
import os
import statistics
import time
import urllib.error
import urllib.request
from pathlib import Path


BASE = os.getenv("PERF_API_URL", "http://127.0.0.1:8000/api/v1").rstrip("/")
OUTPUT = Path(os.getenv("PERF_API_OUTPUT", "test-results/api-performance.json"))
ROUTES = [
    ("homepage", "/public/homepage"),
    ("products", "/public/products"),
    ("product", "/public/products/northstar-writing-desk"),
    ("categories", "/public/categories"),
    ("category", "/public/categories/ai-tools"),
    ("posts", "/public/posts"),
    ("post", "/public/posts/choosing-an-ai-writing-companion"),
]
ADMIN_ROUTES = [
    ("admin_dashboard", "/admin/dashboard"),
    ("admin_products", "/admin/affiliate-products"),
    ("admin_posts", "/admin/posts"),
    ("admin_analytics", "/admin/affiliate-analytics"),
]


def request(path, method="GET", payload=None, token=None):
    body = json.dumps(payload).encode() if payload is not None else None
    headers = {"Accept": "application/json"}
    if body is not None:
        headers["Content-Type"] = "application/json"
    if token:
        headers["Authorization"] = f"Bearer {token}"
    started = time.perf_counter()
    try:
        with urllib.request.urlopen(
            urllib.request.Request(BASE + path, data=body, headers=headers, method=method),
            timeout=30,
        ) as response:
            content = response.read()
            return response.status, content, (time.perf_counter() - started) * 1000
    except urllib.error.HTTPError as error:
        raise RuntimeError(f"{method} {path} returned {error.code}: {error.read()[:300]!r}") from error


def measure(name, route, token=None):
    samples = []
    sizes = []
    for _ in range(4):
        status, content, elapsed = request(route, token=token)
        if status >= 400:
            raise RuntimeError(f"GET {route} returned {status}")
        samples.append(round(elapsed, 2))
        sizes.append(len(content))
    return {
        "name": name,
        "route": route,
        "cold_ms": samples[0],
        "warm_median_ms": round(statistics.median(samples[1:]), 2),
        "response_bytes": sizes[-1],
        "samples_ms": samples,
    }


def main():
    results = [measure(name, route) for name, route in ROUTES]
    email = os.getenv("PERF_ADMIN_EMAIL")
    password = os.getenv("PERF_ADMIN_PASSWORD")
    if email and password:
        status, content, _ = request("/auth/login", "POST", {"email": email, "password": password})
        if status != 200:
            raise RuntimeError(f"Login returned {status}")
        token = json.loads(content)["data"]["token"]
        results.extend(measure(name, route, token) for name, route in ADMIN_ROUTES)

    report = {"generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()), "base": BASE, "results": results}
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(report, indent=2) + "\n")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
