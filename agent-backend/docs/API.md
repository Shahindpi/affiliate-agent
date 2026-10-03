# Dewdora Backend API Documentation

**Version:** v1

**Base URL**

http://localhost:8000/api/v1

---

# Authentication

Authentication uses Laravel Sanctum (Session Authentication).

## Login

POST /login

Request

```json
{
  "email": "admin@example.com",
  "password": "Admin@123456"
}
```

Response

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": {},
    "token": null
  }
}
```

---

## Current User

GET /me

Authentication Required

---

## Logout

POST /logout

Authentication Required

---

# Public API

No authentication required.

---

## Posts

| Method | Endpoint |
|--------|----------|
| GET | `/public/posts` |
| GET | `/public/posts/{slug}` |

### Query Parameters

| Parameter | Description |
|-----------|-------------|
| search | Search title or excerpt. |
| category | Filter by category slug. |
| tag | Filter by tag slug. |
| sort | newest, oldest, popular |
| page | Pagination page. |
| per_page | Items per page (max 50). |

---

## Categories

| Method | Endpoint |
|--------|----------|
| GET | `/public/categories` |
| GET | `/public/categories/{slug}` |

---

## Tags

| Method | Endpoint |
|--------|----------|
| GET | `/public/tags` |
| GET | `/public/tags/{slug}` |

---

## Affiliate Products

| Method | Endpoint |
|--------|----------|
| GET | `/public/products` |
| GET | `/public/products/{slug}` |

### Query Parameters

| Parameter | Description |
|-----------|-------------|
| category | Category slug |
| featured | true / false |
| search | Product search |
| page | Pagination page |
| per_page | Items per page |

---

# Admin API

Authentication Required.

Prefix:

/admin

---

## Dashboard

| Method | Endpoint |
|--------|----------|
| GET | `/admin/dashboard` |
| GET | `/admin/dashboard/analytics` |
| GET | `/admin/dashboard/health` |

---

## Posts

| Method | Endpoint |
|--------|----------|
| GET | `/admin/posts` |
| POST | `/admin/posts` |
| GET | `/admin/posts/{id}` |
| PUT | `/admin/posts/{id}` |
| DELETE | `/admin/posts/{id}` |
| PUT | `/admin/posts/{id}/tags` |
| PUT | `/admin/posts/{id}/affiliate-products` |

---

## Categories

| Method | Endpoint |
|--------|----------|
| GET | `/admin/categories` |
| POST | `/admin/categories` |
| GET | `/admin/categories/{id}` |
| PUT | `/admin/categories/{id}` |
| DELETE | `/admin/categories/{id}` |

---

## Tags

| Method | Endpoint |
|--------|----------|
| GET | `/admin/tags` |
| POST | `/admin/tags` |
| GET | `/admin/tags/{id}` |
| PUT | `/admin/tags/{id}` |
| DELETE | `/admin/tags/{id}` |

---

## Affiliate Products

| Method | Endpoint |
|--------|----------|
| GET | `/admin/affiliate-products` |
| POST | `/admin/affiliate-products` |
| GET | `/admin/affiliate-products/{id}` |
| PUT | `/admin/affiliate-products/{id}` |
| DELETE | `/admin/affiliate-products/{id}` |

---

## SEO Meta

### Post SEO

| Method | Endpoint |
|--------|----------|
| PUT | `/admin/posts/{id}/seo` |
| DELETE | `/admin/posts/{id}/seo` |

### Affiliate Product SEO

| Method | Endpoint |
|--------|----------|
| PUT | `/admin/affiliate-products/{id}/seo` |
| DELETE | `/admin/affiliate-products/{id}/seo` |

---

# Standard Success Response

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {}
}
```

---

# Standard Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": [
      "The field is required."
    ]
  }
}
```

---

# Standard Authentication Error (401)

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

---

# Standard Authorization Error (403)

```json
{
  "success": false,
  "message": "Forbidden."
}
```

---

# Standard Not Found Error (404)

```json
{
  "success": false,
  "message": "Resource not found."
}
```

---

# Standard Server Error (500)

```json
{
  "success": false,
  "message": "Server error."
}
```

---

# Pagination Response

```json
{
  "success": true,
  "message": "Posts retrieved successfully.",
  "data": [],
  "links": {},
  "meta": {}
}
```

---

# Rate Limiting

Public API endpoints use:

**60 requests per minute**

based on authenticated user ID or client IP address.

---

# Project Structure

```
routes/api.php

app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/Admin
│   │   └── Api/Public
│   └── Resources/Api
├── Models
├── Services
├── Support
└── Observers

tests/http/
docs/API.md
```