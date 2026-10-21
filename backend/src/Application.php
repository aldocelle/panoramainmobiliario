<?php
declare(strict_types=1);
namespace Panorama;
final class Application
{
    private Auth $auth;
    private RateLimiter $limiter;
    private PlatformService $platform;
    private array $routes = [];
    public function __construct(private Database $db, private array $config)
    {
        $this->auth = new Auth($db, $config);
        $this->limiter = new RateLimiter($db, $config);
        $workspace = new WorkspaceService($db, $this->auth);
        $properties = new PropertyService($db, $this->auth, $config);
        $crm = new CrmService($db, $this->auth, $config);
        $capture = new CaptureService($db, $this->auth, $crm);
        $projects = new ProjectService($db, $this->auth);
        $recovery = new RecoveryService($db, $config);
        $media = new MediaService($db, $this->auth, $config);
        $favorites = new FavoriteService($db, $this->auth);
        $this->platform = $platform = new PlatformService($db, $this->auth, $config);
        $this->route("POST", "/platform/auth/login", fn($r) => $platform->login($r));
        $this->route("GET", "/platform/auth/session", fn($r) => $platform->session($r));
        $this->route("POST", "/platform/auth/logout", fn($r) => $platform->logout($r));
        $this->route("GET", "/platform/organizations", fn($r) => $platform->organizations($r));
        $this->route("POST", "/platform/organizations", fn($r) => $platform->createOrganization($r));
        $this->route(
            "PUT",
            "/platform/organizations/{id}/subscription",
            fn($r, $id) => $platform->capacity($r, $id),
        );
        $this->route(
            "GET",
            "/platform/organizations/{id}/changes",
            fn($r, $id) => $platform->changes($r, $id),
        );
        $this->route(
            "GET",
            "/health",
            fn() => new Response([
                "status" => "ok",
                "database" =>
                    (int) $this->db->one("SELECT 1 alive")["alive"] === 1
                        ? "ok"
                        : "unavailable",
            ]),
        );
        $this->route(
            "POST",
            "/auth/recovery",
            fn($r) => $recovery->request($r),
        );
        $this->route("POST", "/auth/reset", fn($r) => $recovery->reset($r));
        $this->route("GET", "/favorites", fn($r) => $favorites->list($r));
        $this->route(
            "POST",
            "/favorites/{id}",
            fn($r, $id) => $favorites->add($r, $id),
        );
        $this->route(
            "DELETE",
            "/favorites/{id}",
            fn($r, $id) => $favorites->remove($r, $id),
        );
        $this->route("POST", "/media", fn($r) => $media->upload($r));
        $this->route(
            "GET",
            "/media/{id}",
            fn($r, $id) => $media->serve($r, $id),
        );
        $this->route(
            "DELETE",
            "/media/{id}",
            fn($r, $id) => $media->remove($r, $id),
        );
        $this->route(
            "GET",
            "/public/media/{id}",
            fn($r, $id) => $media->serve($r, $id, true),
        );
        $this->route(
            "POST",
            "/auth/register",
            fn($r) => $this->auth->register($r),
        );
        $this->route("POST", "/auth/login", fn($r) => $this->auth->login($r));
        $this->route("POST", "/auth/logout", fn($r) => $this->auth->logout($r));
        $this->route("GET", "/auth/session", fn($r) => $this->auth->me($r));
        $this->route(
            "PUT",
            "/auth/password",
            fn($r) => $this->auth->changePassword($r),
        );
        $this->route(
            "POST",
            "/auth/organization",
            fn($r) => $this->auth->switchOrganization($r),
        );
        $this->route("GET", "/workspace", fn($r) => $workspace->get($r));
        $this->route("PUT", "/workspace", fn($r) => $workspace->update($r));
        $this->route(
            "GET",
            "/workspace/subscription",
            fn($r) => $workspace->subscription($r),
        );
        $this->route(
            "GET",
            "/workspace/members",
            fn($r) => $workspace->members($r),
        );
        $this->route(
            "POST",
            "/workspace/invitations",
            fn($r) => $workspace->invite($r),
        );
        $this->route(
            "POST",
            "/workspace/invitations/accept",
            fn($r) => $workspace->accept($r),
        );
        $this->route(
            "PUT",
            "/workspace/members/{id}",
            fn($r, $id) => $workspace->changeRole($r, $id),
        );
        $this->route(
            "DELETE",
            "/workspace/members/{id}",
            fn($r, $id) => $workspace->revokeMember($r, $id),
        );
        $this->route(
            "GET",
            "/workspace/dashboard",
            fn($r) => $workspace->dashboard($r),
        );
        $this->route(
            "GET",
            "/workspace/export",
            fn($r) => $workspace->export($r),
        );
        $this->route(
            "GET",
            "/workspace/reports/properties.csv",
            fn($r) => $workspace->export($r, true),
        );
        $this->route(
            "GET",
            "/workspace/audit",
            fn($r) => $workspace->audit($r),
        );
        $this->route("GET", "/properties", fn($r) => $properties->listing($r));
        $this->route("POST", "/properties", fn($r) => $properties->create($r));
        $this->route(
            "GET",
            "/properties/{id}",
            fn($r, $id) => $properties->get($r, $id),
        );
        $this->route(
            "PUT",
            "/properties/{id}",
            fn($r, $id) => $properties->update($r, $id),
        );
        $this->route(
            "DELETE",
            "/properties/{id}",
            fn($r, $id) => $properties->remove($r, $id),
        );
        $this->route("GET", "/leads", fn($r) => $crm->leads($r));
        $this->route(
            "PUT",
            "/leads/{id}",
            fn($r, $id) => $crm->updateLead($r, $id),
        );
        $this->route(
            "DELETE",
            "/leads/{id}",
            fn($r, $id) => $crm->eraseLead($r, $id),
        );
        $this->route("GET", "/visits", fn($r) => $crm->visits($r));
        $this->route("POST", "/visits", fn($r) => $crm->createVisit($r));
        $this->route(
            "PUT",
            "/visits/{id}",
            fn($r, $id) => $crm->updateVisit($r, $id),
        );
        $this->route(
            "DELETE",
            "/visits/{id}",
            fn($r, $id) => $crm->cancelVisit($r, $id),
        );
        $this->route("GET", "/projects", fn($r) => $projects->list($r));
        $this->route("POST", "/projects", fn($r) => $projects->create($r));
        $this->route(
            "PUT",
            "/projects/{id}",
            fn($r, $id) => $projects->update($r, $id),
        );
        $this->route(
            "GET",
            "/projects/{id}/units",
            fn($r, $id) => $projects->units($r, $id),
        );
        $this->route(
            "POST",
            "/projects/{id}/units",
            fn($r, $id) => $projects->createUnit($r, $id),
        );
        $this->route(
            "PUT",
            "/projects/{id}/units/{unit}",
            fn($r, $id, $unit) => $projects->updateUnit($r, $id, $unit),
        );
        $this->route(
            "PUT",
            "/broker-program/profile",
            fn($r) => $capture->enroll($r),
        );
        $this->route(
            "DELETE",
            "/broker-program/profile",
            fn($r) => $capture->withdraw($r),
        );
        $this->route(
            "GET",
            "/broker-program/requests",
            fn($r) => $capture->inbox($r),
        );
        $this->route(
            "PUT",
            "/broker-program/requests/{id}",
            fn($r, $id) => $capture->decision($r, $id),
        );
        $this->route(
            "GET",
            "/public/properties",
            fn($r) => $properties->listing($r, true),
        );
        $this->route(
            "GET",
            "/public/properties/{id}",
            fn($r, $id) => $properties->get($r, $id, true),
        );
        $this->route(
            "POST",
            "/public/properties/{id}/inquiries",
            fn($r, $id) => $crm->createLead($r, $id),
        );
        $this->route(
            "GET",
            "/public/brokers",
            fn($r) => $capture->candidates($r),
        );
        $this->route(
            "POST",
            "/public/owner-requests",
            fn($r) => $capture->createRequest($r),
        );
        $this->route(
            "GET",
            "/public/owner-requests/{id}",
            fn($r, $id) => $capture->manage($r, $id),
        );
        $this->route(
            "DELETE",
            "/public/owner-requests/{id}",
            fn($r, $id) => $capture->manage($r, $id, true),
        );
        $this->route(
            "GET",
            "/public/config",
            fn() => new Response([
                "privacyNoticeVersion" => $config["privacyVersion"],
                "personalDataIntakeEnabled" => $config["privacyVersion"] !== "",
                "registrationEnabled" => $config["registration"],
                "billingEnabled" => false,
                "territory" => [
                    "regions" => 16,
                    "communes" => "manual_unverified",
                ],
            ]),
        );
        $this->route(
            "GET",
            "/public/plans",
            fn() => new Response([
                "items" => [
                    [
                        "id" => "inicio",
                        "name" => "Inicio",
                        "limit" => 5,
                        "monthlyUf" => null,
                    ],
                    [
                        "id" => "profesional",
                        "name" => "Profesional",
                        "limit" => 30,
                        "monthlyUf" => null,
                    ],
                    [
                        "id" => "equipo",
                        "name" => "Equipo",
                        "limit" => 100,
                        "monthlyUf" => null,
                    ],
                ],
                "contractingEnabled" => false,
            ]),
        );
    }
    private function route(
        string $method,
        string $path,
        callable $handler,
    ): void {
        $pattern = preg_quote($this->config["prefix"] . $path, "~");
        $pattern = preg_replace(
            "/\\\\\{(?:id|unit)\\\\\}/",
            "([0-9a-fA-F-]{36})",
            $pattern,
        );
        $this->routes[] = [$method, "~^" . $pattern . '$~D', $handler];
    }
    public function handle(Request $r): Response
    {
        $headers = [
            "X-Request-Id" => $r->id,
            "Cache-Control" => "no-store",
            "X-Content-Type-Options" => "nosniff",
            "X-Frame-Options" => "DENY",
            "Referrer-Policy" => "no-referrer",
            "Content-Security-Policy" =>
                "default-src 'none'; frame-ancestors 'none'",
            "Vary" => "Origin",
        ];
        if ($this->config["production"]) {
            $headers["Strict-Transport-Security"] = "max-age=31536000";
        }
        try {
            $origin = $r->header("origin");
            ApiError::require(
                $origin === "" ||
                    in_array($origin, $this->config["origins"], true),
                403,
                "ORIGIN_FORBIDDEN",
                "Origen no permitido.",
            );
            if ($origin !== "") {
                $headers["Access-Control-Allow-Origin"] = $origin;
                $headers["Access-Control-Allow-Credentials"] = "true";
            }
            if ($r->method === "OPTIONS") {
                return new Response(
                    null,
                    204,
                    $headers + [
                        "Access-Control-Allow-Methods" =>
                            "GET,POST,PUT,DELETE,OPTIONS",
                        "Access-Control-Allow-Headers" =>
                            "Content-Type,X-CSRF-Token,X-Request-Token",
                        "Access-Control-Max-Age" => "600",
                    ],
                );
            }
            // /platform usa solo la sesión de superadmin: la cookie de corredora también llega
            // a esas rutas (comparten prefijo) y no debe autenticar ni exigir su CSRF ahí.
            if (str_starts_with($r->path, $this->config["prefix"] . "/platform/")) {
                $this->platform->attach($r);
                $this->platform->csrf($r);
            } else {
                $this->auth->attach($r);
                $this->auth->csrf($r);
            }
            if ($r->path !== $this->config["prefix"] . "/health") {
                $this->limiter->hit($r, "all", 300, 60);
                if (
                    in_array(
                        $r->path,
                        [
                            $this->config["prefix"] . "/auth/login",
                            $this->config["prefix"] . "/auth/register",
                            $this->config["prefix"] . "/auth/recovery",
                            $this->config["prefix"] . "/auth/reset",
                        ],
                        true,
                    )
                ) {
                    $this->limiter->hit($r, "auth", 15);
                }
                if (
                    $r->path === $this->config["prefix"] . "/platform/auth/login"
                ) {
                    $this->limiter->hit($r, "platform-login", 5);
                }
                if (
                    $r->method === "POST" &&
                    str_contains($r->path, "/public/")
                ) {
                    $this->limiter->hit($r, "public-write", 10);
                }
            }
            if (
                $r->path === $this->config["prefix"] . "/media" &&
                $r->method === "POST"
            ) {
                $this->limiter->hit($r, "upload", 20);
            }
            foreach ($this->routes as [$method, $pattern, $handler]) {
                if (
                    $method === $r->method &&
                    preg_match($pattern, $r->path, $matches)
                ) {
                    array_shift($matches);
                    $response = $handler($r, ...$matches);
                    $response->headers = $headers + $response->headers;
                    return $response;
                }
            }
            throw new ApiError(404, "ROUTE_NOT_FOUND", "Ruta no encontrada.");
        } catch (ApiError $e) {
            return new Response(
                [
                    "error" => [
                        "code" => $e->errorCode,
                        "message" => $e->getMessage(),
                        "details" => $e->details,
                        "requestId" => $r->id,
                    ],
                ],
                $e->status,
                $headers + ($e->status === 429 ? ["Retry-After" => "900"] : []),
            );
        } catch (\PDOException $e) {
            $integrity = in_array(
                (string) $e->getCode(),
                ["23000", "23505"],
                true,
            );
            if (!$integrity) {
                error_log("Panorama " . $r->id . " database_error");
            }
            return new Response(
                [
                    "error" => [
                        "code" => $integrity
                            ? "RECORD_CONFLICT"
                            : "DATABASE_ERROR",
                        "message" => $integrity
                            ? "El registro entra en conflicto con datos existentes."
                            : "No se pudo procesar la solicitud.",
                        "requestId" => $r->id,
                    ],
                ],
                $integrity ? 409 : 503,
                $headers,
            );
        } catch (\Throwable $e) {
            error_log("Panorama " . $r->id . " " . get_class($e));
            return new Response(
                [
                    "error" => [
                        "code" => "INTERNAL_ERROR",
                        "message" => "No se pudo procesar la solicitud.",
                        "requestId" => $r->id,
                    ],
                ],
                500,
                $headers,
            );
        }
    }
}
