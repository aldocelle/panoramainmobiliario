<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use Panorama\{Application, Config, Database, Request, Response, Support, Auth};
$config = Config::read();
if (getenv("PANORAMA_TEST_MYSQL") === "true") {
    if (!str_starts_with($config["database"], "panorama_test_")) {
        throw new RuntimeException(
            "Las pruebas MySQL requieren DB_DATABASE con prefijo panorama_test_.",
        );
    }
} else {
    $config["driver"] = "sqlite";
    $config["dbPath"] = ":memory:";
}
$config["mediaPath"] =
    sys_get_temp_dir() . "/panorama-test-media-" . bin2hex(random_bytes(6));
$config["production"] = false;
$config["registration"] = true;
$config["secure"] = false;
$config["privacyVersion"] = "test-v1";
$config["origins"] = ["http://localhost:5173"];
$db = new Database($config);
$db->migrate();
$app = new Application($db, $config);
$passed = 0;
$failed = 0;
function check(bool $condition, string $message = "Afirmación fallida"): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function eq(mixed $actual, mixed $expected): void
{
    check(
        $actual === $expected,
        "Esperado " .
            json_encode($expected) .
            ", recibido " .
            json_encode($actual),
    );
}
function api(
    string $method,
    string $path,
    array $body = [],
    ?array $session = null,
    array $query = [],
    array $headers = [],
    string $ip = "192.0.2.1",
): Response {
    global $app;
    $h = array_replace(
        [
            "content-type" => "application/json",
            "origin" => "http://localhost:5173",
        ],
        $headers,
    );
    $cookies = [];
    if ($session) {
        $cookies = [Auth::COOKIE => $session["cookie"]];
        $h["x-csrf-token"] = $session["csrf"];
    }
    return $app->handle(
        new Request(
            $method,
            "/api/v1" . $path,
            $h,
            $query,
            $cookies,
            $body,
            $ip,
        ),
    );
}
function session(Response $r): array
{
    check(
        in_array($r->status, [200, 201], true),
        "No se creó sesión: " . json_encode($r->data),
    );
    return [
        "cookie" => $r->cookie["value"],
        "csrf" => $r->data["csrfToken"],
        "user" => $r->data["user"]["id"],
        "org" => $r->data["organizationId"],
    ];
}
function register(string $email, string $kind = "broker"): array
{
    return session(
        api("POST", "/auth/register", [
            "email" => $email,
            "password" => "Strong password 123!",
            "name" => "Prueba Integración",
            "organizationName" => "Organización de prueba",
            "kind" => $kind,
        ]),
    );
}
function test(string $name, callable $fn): void
{
    global $passed, $failed, $db;
    $db->run("DELETE FROM rate_limits");
    try {
        $fn();
        $passed++;
        echo "PASS $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL $name: ", $e->getMessage(), "\n";
    }
}
function sample(array $overrides = []): array
{
    return array_replace(
        [
            "title" => "Casa de prueba",
            "type" => "Casa",
            "operation" => "Venta",
            "currency" => "UF",
            "price" => 3500.25,
            "region" => "05",
            "commune" => "Viña del Mar",
            "sector" => "Centro",
            "privateAddress" => "Dirección privada de prueba",
            "area" => 80,
            "beds" => 2,
            "baths" => 1,
            "description" => "Ejemplo ficticio",
            "images" => [],
            "status" => "Publicada",
        ],
        $overrides,
    );
}
$a = register("owner-a@example.com");
$b = register("owner-b@example.com");
$developer = register("developer@example.com", "developer");
$p = api("POST", "/properties", sample(), $a);
eq($p->status, 201);
$property = $p->data["id"];
test("migraciones idempotentes e integridad referencial", function () use (
    $db,
) {
    $db->migrate();
    eq(
        (int) $db->one("SELECT COUNT(*) total FROM schema_migrations")["total"],
        count(glob(__DIR__ . "/../database/migrations/*.sql")),
    );
    try {
        $db->run("INSERT INTO memberships VALUES(?,?,?,?)", [
            Support::uuid(),
            Support::uuid(),
            "viewer",
            Support::now(),
        ]);
        throw new RuntimeException("Aceptó claves ajenas inexistentes");
    } catch (PDOException) {
    }
});
test(
    "registro no permite roles o límites enviados por el cliente",
    function () {
        eq(
            api("POST", "/auth/register", [
                "name" => "Test",
                "email" => "forged@example.com",
                "password" => "Strong password 123!",
                "organizationName" => "Test org",
                "role" => "owner",
                "publicationLimit" => 999,
            ])->status,
            422,
        );
    },
);
test("registro normaliza email y protege duplicados", function () {
    eq(
        api("POST", "/auth/register", [
            "name" => "Prueba",
            "email" => "OWNER-A@EXAMPLE.COM",
            "password" => "Strong password 123!",
            "organizationName" => "Test org",
        ])->status,
        409,
    );
});
test("credenciales, cookies y sesión", function () use ($a) {
    $r = api("GET", "/auth/session", [], $a);
    eq($r->status, 200);
    eq($r->data["role"], "owner");
    check(!isset($r->data["user"]["password_hash"]));
    eq(
        api("POST", "/auth/login", [
            "email" => "owner-a@example.com",
            "password" => "incorrecta",
        ])->status,
        401,
    );
    $r = api("POST", "/auth/login", [
        "email" => "owner-b@example.com",
        "password" => "Strong password 123!",
    ]);
    eq($r->cookie["options"]["httponly"], true);
    eq($r->cookie["options"]["samesite"], "Lax");
});
test("contraseñas con espacios conservan identidad exacta", function () {
    session(
        api("POST", "/auth/register", [
            "email" => "spaces@example.com",
            "password" => "  contraseña larga 123  ",
            "name" => "Spaces",
            "organizationName" => "Spaces org",
        ]),
    );
    eq(
        api("POST", "/auth/login", [
            "email" => "spaces@example.com",
            "password" => "  contraseña larga 123  ",
        ])->status,
        200,
    );
    eq(
        api("POST", "/auth/login", [
            "email" => "spaces@example.com",
            "password" => "contraseña larga 123",
        ])->status,
        401,
    );
});
test(
    "escrituras autenticadas exigen CSRF y orígenes permitidos",
    function () use ($app, $a) {
        $r = $app->handle(
            new Request(
                "POST",
                "/api/v1/properties",
                ["content-type" => "application/json"],
                [],
                [Auth::COOKIE => $a["cookie"]],
                sample(),
            ),
        );
        eq($r->status, 403);
        eq(
            api(
                "GET",
                "/workspace",
                [],
                $a,
                [],
                ["origin" => "https://evil.example"],
            )->status,
            403,
        );
    },
);
test("sin sesión no hay acceso al espacio privado", function () {
    eq(api("GET", "/properties")->status, 401);
    eq(api("GET", "/workspace/export")->status, 401);
});
test(
    "aislamiento entre organizaciones y error sin filtrado de existencia",
    function () use ($a, $b, $property) {
        eq(api("GET", "/properties/" . $property, [], $b)->status, 404);
        eq(
            api("PUT", "/properties/" . $property, sample(["version" => 1]), $b)
                ->status,
            404,
        );
        eq(
            api("DELETE", "/properties/" . $property, ["version" => 1], $b)
                ->status,
            404,
        );
        eq(api("GET", "/properties", [], $b)->data["total"], 0);
        eq(
            api(
                "POST",
                "/auth/organization",
                ["organizationId" => $a["org"]],
                $b,
            )->status,
            404,
        );
    },
);
test(
    "el portal público no entrega dirección privada ni datos de usuarios",
    function () use ($property) {
        $r = api("GET", "/public/properties/" . $property);
        eq($r->status, 200);
        check(!array_key_exists("privateAddress", $r->data));
        check(!array_key_exists("private_address", $r->data));
        check(!isset($r->data["organization_id"]));
    },
);
test(
    "validación rechaza precios, regiones, URL y campos ajenos",
    function () use ($a) {
        foreach (
            [
                ["price" => -1],
                ["region" => "99"],
                ["images" => ["javascript:alert(1)"]],
                ["organization_id" => "forged"],
                ["currency" => "CLP", "price" => 1.5],
            ]
            as $bad
        ) {
            eq(api("POST", "/properties", sample($bad), $a)->status, 422);
        }
    },
);
test("búsqueda y paginación no interpolan entradas SQL", function () use (
    $property,
) {
    eq(
        api("GET", "/public/properties", [], null, ["q" => "' OR 1=1 --"])
            ->data["total"],
        0,
    );
    eq(
        api("GET", "/public/properties", [], null, ["limit" => "100000"])
            ->status,
        422,
    );
    eq(
        api("GET", "/public/properties", [], null, ["status" => "Borrador"])
            ->status,
        422,
    );
    eq(
        api("GET", "/public/properties", [], null, ["region" => "05"])->data[
            "total"
        ],
        1,
    );
});
test("versionado impide sobrescribir cambios ajenos", function () use (
    $a,
    $property,
) {
    $current = api("GET", "/properties/" . $property, [], $a)->data;
    $r = api(
        "PUT",
        "/properties/" . $property,
        sample(["title" => "Actualizado", "version" => $current["version"]]),
        $a,
    );
    eq($r->status, 200);
    eq(
        api(
            "PUT",
            "/properties/" . $property,
            sample(["version" => $current["version"]]),
            $a,
        )->status,
        409,
    );
});
test(
    "cupos en servidor, borradores libres y pausa libera capacidad",
    function () use ($a, $property) {
        for ($i = 0; $i < 4; $i++) {
            eq(
                api(
                    "POST",
                    "/properties",
                    sample(["title" => "Aviso cupo " . $i]),
                    $a,
                )->status,
                201,
            );
        }
        $r = api("POST", "/properties", sample(), $a);
        eq($r->status, 409);
        eq($r->data["error"]["code"], "PUBLICATION_LIMIT");
        $draft = api(
            "POST",
            "/properties",
            sample(["status" => "Borrador"]),
            $a,
        );
        eq($draft->status, 201);
        eq(api("GET", "/public/properties/" . $draft->data["id"])->status, 404);
        $p = api("GET", "/properties/" . $property, [], $a)->data;
        eq(
            api(
                "PUT",
                "/properties/" . $property,
                sample(["status" => "Pausada", "version" => $p["version"]]),
                $a,
            )->status,
            200,
        );
        eq(api("POST", "/properties", sample(), $a)->status, 201);
        $p = api("GET", "/properties/" . $property, [], $a)->data;
        eq(
            api(
                "PUT",
                "/properties/" . $property,
                sample(["status" => "Publicada", "version" => $p["version"]]),
                $a,
            )->status,
            409,
        );
    },
);
// Fresh published record in B for CRM scenarios.
$crmProperty = api("POST", "/properties", sample(), $b)->data["id"];
$inquiry = [
    "name" => "Cliente ficticio",
    "email" => "lead@example.com",
    "phone" => "+56911111111",
    "note" => "Quiero conocer el inmueble",
    "consent" => true,
    "consentVersion" => "test-v1",
];
test(
    "consultas requieren consentimiento vigente y aviso público",
    function () use ($crmProperty, $inquiry, $a, $property) {
        eq(
            api(
                "POST",
                "/public/properties/" . $crmProperty . "/inquiries",
                array_replace($inquiry, ["consent" => false]),
            )->status,
            422,
        );
        eq(
            api(
                "POST",
                "/public/properties/" . $crmProperty . "/inquiries",
                array_replace($inquiry, ["consentVersion" => "old"]),
            )->status,
            422,
        );
        eq(
            api(
                "POST",
                "/public/properties/" . $property . "/inquiries",
                $inquiry,
            )->status,
            404,
        );
        eq(
            api(
                "POST",
                "/public/properties/" . $crmProperty . "/inquiries",
                array_replace($inquiry, ["website" => "spam"]),
            )->status,
            422,
        );
    },
);
$created = api(
    "POST",
    "/public/properties/" . $crmProperty . "/inquiries",
    $inquiry,
);
eq($created->status, 201);
$lead = $created->data["id"];
test(
    "interesados se asignan al dueño del aviso sin filtración entre cuentas",
    function () use ($a, $b, $lead) {
        $rows = api("GET", "/leads", [], $b);
        eq(count($rows->data["items"]), 1);
        eq(count(api("GET", "/leads", [], $a)->data["items"]), 0);
        eq(
            api(
                "PUT",
                "/leads/" . $lead,
                ["stage" => "Contactado", "note" => "test", "version" => 1],
                $a,
            )->status,
            404,
        );
    },
);
$start = time() + 86400;
$visit = [
    "propertyId" => $crmProperty,
    "leadId" => $lead,
    "agentId" => $b["user"],
    "startsAt" => gmdate("Y-m-d\TH:i:s\Z", $start),
    "endsAt" => gmdate("Y-m-d\TH:i:s\Z", $start + 3600),
    "note" => "Visita de prueba",
];
$scheduled = api("POST", "/visits", $visit, $b);
eq($scheduled->status, 201);
$visitId = $scheduled->data["id"];
test(
    "agenda bloquea solapamientos pero permite horarios contiguos",
    function () use ($b, $visit, $start) {
        eq(
            api(
                "POST",
                "/visits",
                array_replace($visit, [
                    "startsAt" => gmdate("Y-m-d\TH:i:s\Z", $start + 1800),
                    "endsAt" => gmdate("Y-m-d\TH:i:s\Z", $start + 5400),
                ]),
                $b,
            )->status,
            409,
        );
        eq(
            api(
                "POST",
                "/visits",
                array_replace($visit, [
                    "startsAt" => gmdate("Y-m-d\TH:i:s\Z", $start + 3600),
                    "endsAt" => gmdate("Y-m-d\TH:i:s\Z", $start + 7200),
                ]),
                $b,
            )->status,
            201,
        );
    },
);
test(
    "agenda exige zona horaria, fechas reales y profesional de la organización",
    function () use ($a, $b, $visit) {
        eq(
            api(
                "POST",
                "/visits",
                array_replace($visit, ["startsAt" => "2027-01-01T10:00:00"]),
                $b,
            )->status,
            422,
        );
        eq(
            api(
                "POST",
                "/visits",
                array_replace($visit, ["startsAt" => "2027-02-30T10:00:00Z"]),
                $b,
            )->status,
            422,
        );
        eq(
            api(
                "POST",
                "/visits",
                array_replace($visit, ["agentId" => $a["user"]]),
                $b,
            )->status,
            404,
        );
    },
);
test("cancelación de agenda exige versión y aislamiento", function () use (
    $a,
    $b,
    $visitId,
) {
    eq(api("DELETE", "/visits/" . $visitId, ["version" => 1], $a)->status, 404);
    eq(api("DELETE", "/visits/" . $visitId, ["version" => 1], $b)->status, 200);
    eq(api("DELETE", "/visits/" . $visitId, ["version" => 1], $b)->status, 409);
});
$c = register("viewer@example.com");
$invite = api(
    "POST",
    "/workspace/invitations",
    ["email" => "viewer@example.com", "role" => "viewer"],
    $a,
);
eq($invite->status, 201);
test("invitación exige destinatario y no se puede reutilizar", function () use (
    $invite,
    $b,
    $c,
) {
    eq(
        api(
            "POST",
            "/workspace/invitations/accept",
            ["token" => $invite->data["token"]],
            $b,
        )->status,
        403,
    );
    eq(
        api(
            "POST",
            "/workspace/invitations/accept",
            ["token" => $invite->data["token"]],
            $c,
        )->status,
        200,
    );
    eq(
        api(
            "POST",
            "/workspace/invitations/accept",
            ["token" => $invite->data["token"]],
            $c,
        )->status,
        403,
    );
});
$viewer = session(
    api("POST", "/auth/organization", ["organizationId" => $a["org"]], $c),
);
test(
    "rol lectura no publica, no cambia marca y no exporta contactos",
    function () use ($viewer) {
        eq(api("GET", "/properties", [], $viewer)->status, 200);
        eq(api("POST", "/properties", sample(), $viewer)->status, 403);
        eq(
            api(
                "PUT",
                "/workspace",
                ["name" => "Fraude", "logo" => ""],
                $viewer,
            )->status,
            403,
        );
        eq(api("GET", "/workspace/export", [], $viewer)->status, 403);
        eq(
            api(
                "POST",
                "/workspace/invitations",
                ["email" => "x@example.com", "role" => "admin"],
                $viewer,
            )->status,
            403,
        );
    },
);
test(
    "propietario protegido y revocación de miembro inmediata",
    function () use ($a, $viewer, $c) {
        eq(
            api(
                "PUT",
                "/workspace/members/" . $a["user"],
                ["role" => "viewer"],
                $a,
            )->status,
            409,
        );
        eq(
            api("DELETE", "/workspace/members/" . $c["user"], [], $a)->status,
            204,
        );
        eq(api("GET", "/workspace", [], $viewer)->status, 401);
    },
);
test(
    "inmobiliarias administran proyectos y unidades separados de corredores",
    function () use ($a, $developer) {
        eq(
            api(
                "POST",
                "/projects",
                [
                    "name" => "Proyecto prueba",
                    "region" => "13",
                    "commune" => "Santiago",
                ],
                $a,
            )->status,
            403,
        );
        $r = api(
            "POST",
            "/projects",
            [
                "name" => "Proyecto prueba",
                "region" => "13",
                "commune" => "Santiago",
            ],
            $developer,
        );
        eq($r->status, 201);
        $id = $r->data["id"];
        $unit = [
            "code" => "A101",
            "type" => "Departamento",
            "currency" => "UF",
            "price" => 5000,
            "area" => 70,
            "beds" => 2,
            "baths" => 2,
        ];
        eq(
            api("POST", "/projects/" . $id . "/units", $unit, $developer)
                ->status,
            201,
        );
        eq(
            api("POST", "/projects/" . $id . "/units", $unit, $developer)
                ->status,
            409,
        );
        eq(
            count(
                api("GET", "/projects/" . $id . "/units", [], $developer)->data[
                    "items"
                ],
            ),
            1,
        );
    },
);
$profile = [
    "regions" => ["05"],
    "communes" => ["Viña del Mar"],
    "operations" => ["Venta", "Arriendo"],
    "types" => ["Casa", "Departamento"],
    "presentation" => "Equipo de corretaje de prueba, cobertura declarada.",
    "contactEmail" => "capture@example.com",
];
test(
    "directorio muestra cobertura declarada sin email de captación ni verificación falsa",
    function () use ($a, $profile) {
        eq(api("PUT", "/broker-program/profile", $profile, $a)->status, 200);
        $r = api("GET", "/public/brokers", [], null, [
            "region" => "05",
            "commune" => "Vina del Mar",
        ]);
        eq($r->data["total"], 1);
        eq($r->data["items"][0]["identityVerified"], false);
        check(!isset($r->data["items"][0]["contact_email"]));
    },
);
$owner = [
    "name" => "Propietario prueba",
    "email" => "owner-request@example.com",
    "phone" => "+56922222222",
    "region" => "05",
    "commune" => "Viña del Mar",
    "operation" => "Venta",
    "type" => "Casa",
    "note" => "Necesito ayuda",
    "brokerIds" => [$a["org"]],
    "consent" => true,
    "consentVersion" => "test-v1",
];
$ownerResponse = api("POST", "/public/owner-requests", $owner);
eq($ownerResponse->status, 201);
$requestId = $ownerResponse->data["id"];
$manageToken = $ownerResponse->data["managementToken"];
$pending = api("GET", "/broker-program/requests", [], $a)->data["items"][0];
test(
    "captación pendiente no entrega contacto y solo destinatarios elegidos acceden",
    function () use ($pending, $a, $b, $owner) {
        check(!isset($pending["email"], $pending["name"], $pending["phone"], $pending["note"]));
        eq(
            count(
                api("GET", "/broker-program/requests", [], $b)->data["items"],
            ),
            0,
        );
        eq(
            api(
                "POST",
                "/public/owner-requests",
                array_replace($owner, ["commune" => "Temuco"]),
            )->status,
            422,
        );
        eq(
            api(
                "PUT",
                "/broker-program/requests/" . $pending["id"],
                ["decision" => "accepted"],
                $b,
            )->status,
            404,
        );
    },
);
test("aceptar captación entrega contacto al corredor elegido", function () use (
    $pending,
    $a,
) {
    eq(
        api(
            "PUT",
            "/broker-program/requests/" . $pending["id"],
            ["decision" => "accepted"],
            $a,
        )->status,
        200,
    );
    $item = api("GET", "/broker-program/requests", [], $a)->data["items"][0];
    eq($item["email"], "owner-request@example.com");
    eq(
        api(
            "PUT",
            "/broker-program/requests/" . $pending["id"],
            ["decision" => "accepted"],
            $a,
        )->status,
        409,
    );
});
test(
    "propietario gestiona y cancela con token privado, sin acceso por ID solo",
    function () use ($requestId, $manageToken, $a) {
        eq(api("GET", "/public/owner-requests/" . $requestId)->status, 401);
        $r = api(
            "DELETE",
            "/public/owner-requests/" . $requestId,
            [],
            null,
            [],
            ["x-request-token" => $manageToken],
        );
        eq($r->status, 200);
        eq($r->data["recipients"][0]["status"], "cancelled");
        check(
            !isset(
                api("GET", "/broker-program/requests", [], $a)->data[
                    "items"
                ][0]["email"],
            ),
        );
    },
);
test(
    "baja del programa retira directorio y bloquea nuevas solicitudes",
    function () use ($a, $owner) {
        eq(api("DELETE", "/broker-program/profile", [], $a)->status, 200);
        eq(api("GET", "/public/brokers")->data["total"], 0);
        eq(api("POST", "/public/owner-requests", $owner)->status, 404);
    },
);
test(
    "anonimización conserva historial sin contacto ni notas y cancela agenda pendiente",
    function () use ($b, $lead) {
        $row = api("GET", "/leads", [], $b)->data["items"][0];
        eq(
            api(
                "DELETE",
                "/leads/" . $lead,
                ["version" => (int) $row["version"]],
                $b,
            )->status,
            204,
        );
        $row = api("GET", "/leads", [], $b)->data["items"][0];
        eq($row["email"], "");
        eq($row["note"], "");
        foreach (api("GET", "/visits", [], $b)->data["items"] as $v) {
            check($v["status"] !== "scheduled");
        }
    },
);
test(
    "reporte CSV neutraliza fórmulas y exportación no incluye sesiones ni hashes",
    function () use ($a) {
        $row = api(
            "POST",
            "/properties",
            sample([
                "title" => "=IMPORTDATA malicious",
                "status" => "Borrador",
            ]),
            $a,
        );
        eq($row->status, 201);
        $r = api("GET", "/workspace/reports/properties.csv", [], $a);
        eq($r->status, 200);
        check(str_contains($r->raw, "'=IMPORTDATA"));
        $r = api("GET", "/workspace/export", [], $a);
        check(!isset($r->data["sessions"], $r->data["users"]));
    },
);
test(
    "auditoría se limita a organización y no almacena texto de consultas",
    function () use ($a, $b, $db) {
        $audit = api("GET", "/workspace/audit", [], $a);
        check(count($audit->data["items"]) > 0);
        foreach ($audit->data["items"] as $event) {
            check(!isset($event["email"], $event["password"], $event["note"]));
        }
        $orgs = $db->all("SELECT DISTINCT organization_id FROM audit_events");
        check(count($orgs) >= 2);
    },
);
test(
    "cliente no puede autoactivar pagos ni ampliar capacidad",
    function () use ($a) {
        eq(
            api(
                "PUT",
                "/workspace/subscription",
                ["publicationLimit" => 999],
                $a,
            )->status,
            404,
        );
        eq(api("GET", "/workspace/subscription", [], $a)->data["limit"], 5);
        eq(api("GET", "/public/plans")->data["contractingEnabled"], false);
    },
);
test("suspensión de plan impide activar nuevos avisos", function () use (
    $b,
    $db,
) {
    $db->run(
        "UPDATE subscriptions SET status='suspended' WHERE organization_id=?",
        [$b["org"]],
    );
    $r = api("POST", "/properties", sample(), $b);
    eq($r->status, 409);
    eq($r->data["error"]["code"], "SUBSCRIPTION_INACTIVE");
    $db->run(
        "UPDATE subscriptions SET status='pilot' WHERE organization_id=?",
        [$b["org"]],
    );
});
test(
    "suspensión retira avisos públicos y rechaza nuevas consultas",
    function () use ($b, $db, $crmProperty, $inquiry) {
        $db->run(
            "UPDATE subscriptions SET status='suspended' WHERE organization_id=?",
            [$b["org"]],
        );
        eq(api("GET", "/public/properties/" . $crmProperty)->status, 404);
        eq(
            api(
                "POST",
                "/public/properties/" . $crmProperty . "/inquiries",
                $inquiry,
            )->status,
            404,
        );
        $db->run(
            "UPDATE subscriptions SET status='pilot' WHERE organization_id=?",
            [$b["org"]],
        );
    },
);
test("favoritos son privados por usuario e idempotentes", function () use (
    $a,
    $b,
    $crmProperty,
) {
    eq(api("POST", "/favorites/" . $crmProperty, [], $a)->status, 200);
    check(
        in_array(
            api("POST", "/favorites/" . $crmProperty, [], $a)->status,
            [200, 201],
            true,
        ),
    );
    eq(count(api("GET", "/favorites", [], $a)->data["items"]), 1);
    eq(count(api("GET", "/favorites", [], $b)->data["items"]), 0);
    eq(api("DELETE", "/favorites/" . $crmProperty, [], $a)->status, 204);
    eq(count(api("GET", "/favorites", [], $a)->data["items"]), 0);
});
test(
    "imágenes se recodifican, quedan privadas sin aviso y exigen organización propietaria",
    function () use ($a, $b) {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);
        $r = api(
            "POST",
            "/media",
            ["contentBase64" => base64_encode($png)],
            $a,
        );
        eq($r->status, 201);
        $id = $r->data["id"];
        eq($r->data["mime"], "image/webp");
        eq(api("GET", "/public/media/" . $id)->status, 404);
        eq(api("GET", "/media/" . $id, [], $a)->status, 200);
        eq(api("GET", "/media/" . $id, [], $b)->status, 404);
        eq(
            api(
                "POST",
                "/properties",
                sample(["status" => "Borrador", "images" => [$r->data["url"]]]),
                $b,
            )->status,
            404,
        );
        $p = api(
            "POST",
            "/properties",
            sample(["status" => "Borrador", "images" => [$r->data["url"]]]),
            $a,
        );
        eq($p->status, 201);
        eq(api("DELETE", "/media/" . $id, [], $a)->status, 409);
        eq(
            api("DELETE", "/properties/" . $p->data["id"], ["version" => 1], $a)
                ->status,
            204,
        );
        eq(api("DELETE", "/media/" . $id, [], $a)->status, 204);
        eq(
            api(
                "POST",
                "/media",
                ["contentBase64" => base64_encode('<svg onload="alert(1)"/>')],
                $a,
            )->status,
            422,
        );
    },
);
test(
    "recuperación cifrada no revela cuentas y revoca sesiones con token de un uso",
    function () use ($db, $config) {
        $c = $config;
        $c["mailTransport"] = "database";
        $c["webBase"] = "http://localhost:5173";
        $auth = new Auth($db, $c);
        $recover = new Panorama\RecoveryService($db, $c);
        $user = register("recovery@example.com");
        $unknown = $recover->request(
            new Request(
                "POST",
                "/",
                [],
                [],
                [],
                ["email" => "missing@example.com"],
            ),
        );
        $known = $recover->request(
            new Request(
                "POST",
                "/",
                [],
                [],
                [],
                ["email" => "recovery@example.com"],
            ),
        );
        eq($unknown->data, $known->data);
        $queued = $db->one("SELECT * FROM mail_outbox");
        check(!str_contains($queued["payload_cipher"], "recovery@example.com"));
        $message = Panorama\RecoveryService::decrypt(
            $queued["payload_cipher"],
            $c["key"],
        );
        parse_str(parse_url($message["body"], PHP_URL_FRAGMENT) ?? "", $unused);
        preg_match("/token=([A-Za-z0-9_-]+)/", $message["body"], $match);
        check(isset($match[1]));
        $req = new Request(
            "POST",
            "/",
            [],
            [],
            [],
            ["token" => $match[1], "password" => "Nueva contraseña segura 123"],
        );
        eq($recover->reset($req)->status, 200);
        eq(api("GET", "/auth/session", [], $user)->status, 401);
        try {
            $recover->reset($req);
            throw new RuntimeException("Reutilizó token");
        } catch (Panorama\ApiError $e) {
            eq($e->status, 400);
        }
        eq(
            api("POST", "/auth/login", [
                "email" => "recovery@example.com",
                "password" => "Nueva contraseña segura 123",
            ])->status,
            200,
        );
    },
);
test("límite de intentos de autenticación es persistente", function () {
    for ($i = 0; $i < 15; $i++) {
        eq(
            api(
                "POST",
                "/auth/login",
                ["email" => "unknown@example.com", "password" => "invalid"],
                null,
                [],
                [],
                "198.51.100.77",
            )->status,
            401,
        );
    }
    eq(
        api(
            "POST",
            "/auth/login",
            ["email" => "unknown@example.com", "password" => "invalid"],
            null,
            [],
            [],
            "198.51.100.77",
        )->status,
        429,
    );
});
test(
    "cambio de contraseña invalida sesiones y verifica contraseña anterior",
    function () use ($b) {
        $r = api(
            "PUT",
            "/auth/password",
            [
                "currentPassword" => "incorrecta",
                "password" => "Otra contraseña 123",
            ],
            $b,
        );
        eq($r->status, 401);
        $r = api(
            "PUT",
            "/auth/password",
            [
                "currentPassword" => "Strong password 123!",
                "password" => "Otra contraseña 123",
            ],
            $b,
        );
        eq($r->status, 200);
        eq(api("GET", "/auth/session", [], $b)->status, 401);
        $fresh = session($r);
        eq(api("POST", "/auth/logout", [], $fresh)->status, 204);
        eq(api("GET", "/auth/session", [], $fresh)->status, 401);
    },
);
test("características opcionales se validan y conservan", function () use (
    $a,
) {
    $details = [
        "parking" => 1,
        "storage" => 1,
        "usefulArea" => 104,
        "commonExpenses" => 240000,
        "floor" => 11,
        "yearBuilt" => 1999,
        "furnished" => "Sin amoblar",
        "pets" => "Consultar",
        "amenities" => ["Piscina", "Quincho", "Piscina"],
    ];
    $r = api(
        "POST",
        "/properties",
        sample(["type" => "Parcela", "status" => "Borrador", "details" => $details]),
        $a,
    );
    eq($r->status, 201);
    $saved = json_decode(json_encode($r->data["details"]), true);
    eq($saved["amenities"], ["Piscina", "Quincho"]);
    eq($saved["commonExpenses"], 240000);
    eq(json_encode(api("POST", "/properties", sample(["status" => "Borrador"]), $a)->data["details"]), "{}");
    foreach (
        [
            ["parking" => -1],
            ["floor" => 1.5],
            ["pets" => "Quizás"],
            ["sauna" => true],
            ["amenities" => [str_repeat("x", 81)]],
            ["yearBuilt" => 1700],
        ]
        as $bad
    ) {
        eq(
            api("POST", "/properties", sample(["status" => "Borrador", "details" => $bad]), $a)->status,
            422,
        );
    }
    eq(api("POST", "/properties", sample(["status" => "Borrador", "details" => [1, 2]]), $a)->status, 422);
});
echo "\n$passed pruebas correctas; $failed fallidas. Driver: ",
    $config["driver"],
    "\n";
if (is_dir($config["mediaPath"])) {
    foreach (glob($config["mediaPath"] . "/*") as $f) {
        unlink($f);
    }
    rmdir($config["mediaPath"]);
}
exit($failed > 0 ? 1 : 0);
