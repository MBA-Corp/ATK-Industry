# Backend hardening contract

All existing authenticated /api/v1 routes reject users whose is_active flag has
become false, including previously authenticated sessions and Sanctum tokens:
HTTP 403, JSON message "Account is inactive." Active user routes and payloads
remain unchanged. GET /api/v1/health remains public.

Dependency patches do not change the API version. Laravel Boost is a development
dependency required by the repository's agent bootstrap; it must not be exposed
in production. Use composer install --no-dev for a deployment artifact.

The development environment and isolated verification environment are documented
in docs/deployment.md and docs/testing.md. These are distinct Compose projects.
