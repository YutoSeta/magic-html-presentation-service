# Magic HTML Presentation Service

A stateless Tier 0 service that deterministically maps a finite provider-neutral Component AST to canonical HTML, compiled Tailwind CSS, inline Heroicons, immutable required-asset metadata, and a source map.

## Boundary

```text
Site AST + Vocabulary AST + Resource Contract + Design intent
                            |
                            v
                    Component AST
                            |
                    Adapter Profile
       Tailwind CSS / Heroicons / font / image
                            |
                            v
       canonical HTML + CSS + assets + source map
```

Requests cannot supply CSS classes, executable templates, PHP/JavaScript class names, package URLs, remote catalog URLs, provider credentials, or arbitrary asset URLs. Image and self-hosted font bytes remain owned by Media or another explicit asset service; this service accepts only normalized relative paths and SHA-256 digests and never fetches them.

The built-in component adapter uses original finite Tailwind CSS compositions. Tailwind Plus is an optional private bring-your-own catalog extension point and no proprietary component source is redistributed here.

## API

- `GET /up` — lightweight liveness
- `GET /api` — capability and adapter inventory
- `GET /api/__verify` — supported contract version and generated-adapter readiness
- `POST /api/v1/presentations/materialize` — synchronous deterministic materialization; Bearer token required

The POST is side-effect free and intentionally does not use an `Idempotency-Key`. Identical canonical input and pinned adapter assets produce the same digest.

## Generated adapter assets

Heroicons 2.2.0 SVG sources and Tailwind CSS v4 output are generated from pinned development dependencies:

```bash
npm ci
npm run build:presentation-assets
```

The generated runtime files are committed so production does not need Node.js.

## Verification

```bash
composer install
npm ci
npm run build
php artisan test --compact
vendor/bin/pint --format agent
composer validate --strict
composer audit --no-dev
```
