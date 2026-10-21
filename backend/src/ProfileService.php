<?php
declare(strict_types=1);
namespace Panorama;
/**
 * Página pública de cada corredora: la edita su equipo desde el panel y el portal la muestra en
 * #corredora/{slug}. Las cifras se calculan siempre desde los avisos publicados.
 */
final class ProfileService
{
    private const FIELDS = ["slug", "tagline", "presentation", "coverUrl", "logoUrl", "phone", "whatsapp", "email", "website", "hours", "officeRegion", "officeCommune", "coverage"];
    public function __construct(
        private Database $db,
        private Auth $auth,
        private array $config,
    ) {}
    public static function slugify(string $value): string
    {
        $text = strtr(Support::normalize($value), ["à" => "a", "è" => "e", "ì" => "i", "ò" => "o", "ù" => "u"]);
        return trim(substr(trim(preg_replace('/[^a-z0-9]+/', "-", $text), "-"), 0, 60), "-");
    }
    public function mine(Request $r): Response
    {
        $a = $this->auth->require($r);
        return new Response($this->serialize($this->load($a["organizationId"]), false));
    }
    public function save(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        $d = $r->body;
        Validator::keys($d, self::FIELDS);
        $slug = Validator::string($d, "slug", 3, 60);
        ApiError::require(preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1, 422, "VALIDATION_ERROR", "La dirección solo admite minúsculas, números y guiones.");
        $phone = $this->phone($d, "phone");
        $whatsapp = $this->phone($d, "whatsapp");
        $email = ($d["email"] ?? "") === "" ? "" : Validator::email($d, "email");
        $website = Validator::string($d, "website", 0, 300, "");
        if ($website !== "") {
            ApiError::require(filter_var($website, FILTER_VALIDATE_URL) !== false && parse_url($website, PHP_URL_SCHEME) === "https", 422, "VALIDATION_ERROR", "El sitio web debe comenzar con https://.");
        }
        $region = Validator::string($d, "officeRegion", 0, 2, "");
        ApiError::require($region === "" || in_array($region, Validator::REGIONS, true), 422, "VALIDATION_ERROR", "officeRegion no permitido.");
        $coverage = Validator::list(["coverage" => $d["coverage"] ?? []], "coverage", 0, 30, fn($v) => Validator::string(["value" => $v], "value", 2, 100));
        $row = [
            $slug,
            Validator::string($d, "tagline", 0, 140, ""),
            Validator::string($d, "presentation", 0, 1500, ""),
            $this->image($d, "coverUrl", $a["organizationId"]),
            $this->image($d, "logoUrl", $a["organizationId"]),
            $phone,
            $whatsapp,
            $email,
            $website,
            Validator::string($d, "hours", 0, 140, ""),
            $region,
            Validator::string($d, "officeCommune", 0, 100, ""),
            Support::json(array_values(array_unique($coverage))),
            Support::now(),
        ];
        $this->db->transaction(function () use ($r, $a, $slug, $row) {
            $this->db->lockOrganization($a["organizationId"]);
            $taken = $this->db->one("SELECT organization_id FROM public_profiles WHERE slug=? AND organization_id<>?", [$slug, $a["organizationId"]]);
            ApiError::require($taken === null && !$this->slugUsedByName($slug, $a["organizationId"]), 409, "SLUG_TAKEN", "Esa dirección ya la usa otra corredora. Prueba con otra.");
            $exists = $this->db->one("SELECT organization_id FROM public_profiles WHERE organization_id=?", [$a["organizationId"]]);
            if ($exists) {
                $this->db->run("UPDATE public_profiles SET slug=?,tagline=?,presentation=?,cover_url=?,logo_url=?,phone=?,whatsapp=?,email=?,website=?,hours=?,office_region=?,office_commune=?,coverage_json=?,updated_at=? WHERE organization_id=?", [...$row, $a["organizationId"]]);
            } else {
                $this->db->run("INSERT INTO public_profiles VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [$a["organizationId"], ...$row]);
            }
            Support::audit($this->db, $r, "profile.updated", "organization", $a["organizationId"]);
        });
        return new Response($this->serialize($this->load($a["organizationId"]), false));
    }
    public function page(Request $r, string $slug): Response
    {
        $org = Support::record($this->resolve($slug));
        return new Response($this->serialize($this->load($org["id"]), true));
    }
    /** Vista previa para WhatsApp y redes; a las personas las lleva a #corredora/{slug}. */
    public function share(Request $r, string $slug): Response
    {
        $site = rtrim($this->config["origins"][0] ?? "", "/") . preg_replace('~/api/v1$~', "", $this->config["prefix"]);
        $origin = rtrim($this->config["origins"][0] ?? "", "/");
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, "UTF-8");
        $org = $this->resolve($slug);
        if (!$org) {
            [$title, $description, $image, $target] = ["Panorama Inmobiliario — Tu próximo lugar", "Casas, departamentos, oficinas y terrenos en venta y arriendo en todo Chile.", "$site/og-image.jpg", "$site/"];
        } else {
            $p = $this->serialize($this->load($org["id"]), true);
            $title = $p["name"];
            $parts = array_filter([$p["tagline"], $p["stats"]["published"] . ($p["stats"]["published"] === 1 ? " propiedad publicada" : " propiedades publicadas"), $p["officeCommune"]]);
            $description = implode(" · ", $parts);
            $cover = $p["coverUrl"] ?: ($p["stats"]["firstImage"] ?? "");
            $image = match (true) {
                str_starts_with($cover, "/") => $origin . $cover . "?size=share",
                str_starts_with($cover, "https://") => $cover,
                default => "$site/og-image.jpg",
            };
            $target = "$site/#corredora/" . $p["slug"];
        }
        $url = $origin . $this->config["prefix"] . "/public/broker-share/" . rawurlencode($slug);
        $html = "<!doctype html><html lang=\"es-CL\"><head><meta charset=\"utf-8\"><title>" . $e($title) . " · Panorama Inmobiliario</title>" .
            "<meta name=\"description\" content=\"" . $e($description) . "\">" .
            "<meta property=\"og:type\" content=\"profile\"><meta property=\"og:site_name\" content=\"Panorama Inmobiliario\"><meta property=\"og:locale\" content=\"es_CL\">" .
            "<meta property=\"og:url\" content=\"" . $e($url) . "\"><meta property=\"og:title\" content=\"" . $e($title) . "\">" .
            "<meta property=\"og:description\" content=\"" . $e($description) . "\"><meta property=\"og:image\" content=\"" . $e($image) . "\">" .
            "<meta property=\"og:image:secure_url\" content=\"" . $e($image) . "\"><meta name=\"twitter:card\" content=\"summary_large_image\">" .
            "<meta http-equiv=\"refresh\" content=\"0;url=" . $e($target) . "\"></head><body><p><a href=\"" . $e($target) . "\">Ver " . $e($title) . " en Panorama Inmobiliario</a></p></body></html>";
        return new Response(null, 200, ["Content-Type" => "text/html; charset=utf-8", "Cache-Control" => "public, max-age=600"], null, $html);
    }
    /** Corredora visible: suscripción vigente; slug propio o, sin perfil guardado, derivado del nombre. */
    private function resolve(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 60) {
            return null;
        }
        $active = "JOIN subscriptions s ON s.organization_id=o.id WHERE s.status IN ('pilot','active')";
        $own = $this->db->one("SELECT o.* FROM organizations o JOIN public_profiles pp ON pp.organization_id=o.id $active AND pp.slug=?", [$slug]);
        if ($own) {
            return $own;
        }
        foreach ($this->db->all("SELECT o.* FROM organizations o LEFT JOIN public_profiles pp ON pp.organization_id=o.id $active AND pp.organization_id IS NULL") as $org) {
            if (self::slugify($org["name"]) === $slug) {
                return $org;
            }
        }
        return null;
    }
    private function slugUsedByName(string $slug, string $except): bool
    {
        foreach ($this->db->all("SELECT o.id,o.name FROM organizations o LEFT JOIN public_profiles pp ON pp.organization_id=o.id WHERE pp.organization_id IS NULL AND o.id<>?", [$except]) as $org) {
            if (self::slugify($org["name"]) === $slug) {
                return true;
            }
        }
        return false;
    }
    private function load(string $org): array
    {
        $o = Support::record($this->db->one("SELECT * FROM organizations WHERE id=?", [$org]));
        $p = $this->db->one("SELECT * FROM public_profiles WHERE organization_id=?", [$org]);
        return ["org" => $o, "profile" => $p];
    }
    private function serialize(array $data, bool $public): array
    {
        $o = $data["org"];
        $p = $data["profile"] ?? [];
        $out = [
            "saved" => $data["profile"] !== null,
            "slug" => $p["slug"] ?? self::slugify($o["name"]),
            "name" => $o["name"],
            "logoUrl" => ($p["logo_url"] ?? "") ?: $o["logo_url"],
            "joinedAt" => $o["created_at"],
            "tagline" => $p["tagline"] ?? "",
            "presentation" => $p["presentation"] ?? "",
            "coverUrl" => $p["cover_url"] ?? "",
            "phone" => $p["phone"] ?? "",
            "whatsapp" => $p["whatsapp"] ?? "",
            "email" => $p["email"] ?? "",
            "website" => $p["website"] ?? "",
            "hours" => $p["hours"] ?? "",
            "officeRegion" => $p["office_region"] ?? "",
            "officeCommune" => $p["office_commune"] ?? "",
            "coverage" => isset($p["coverage_json"]) ? json_decode($p["coverage_json"], true) : [],
            "coverageSource" => "self_declared",
        ];
        if ($public) {
            unset($out["saved"]);
            $out["stats"] = $this->stats($o["id"]);
        }
        return $out;
    }
    private function stats(string $org): array
    {
        $rows = $this->db->all("SELECT operation,commune,images_json FROM properties WHERE organization_id=? AND status='Publicada' AND deleted_at IS NULL ORDER BY created_at DESC,id", [$org]);
        $communes = [];
        foreach ($rows as $row) {
            $communes[$row["commune"]] = ($communes[$row["commune"]] ?? 0) + 1;
        }
        arsort($communes);
        $first = "";
        foreach ($rows as $row) {
            $first = json_decode($row["images_json"], true)[0] ?? "";
            if ($first !== "") {
                break;
            }
        }
        return [
            "published" => count($rows),
            "sale" => count(array_filter($rows, fn($row) => $row["operation"] === "Venta")),
            "rent" => count(array_filter($rows, fn($row) => $row["operation"] === "Arriendo")),
            "communes" => array_map(fn($name, $count) => ["commune" => $name, "count" => $count], array_keys($communes), array_values($communes)),
            "firstImage" => $first,
        ];
    }
    private function phone(array $d, string $key): string
    {
        $v = Validator::string($d, $key, 0, 30, "");
        ApiError::require($v === "" || preg_match('/^\+?[0-9][0-9 ()-]{7,19}$/', $v) === 1, 422, "VALIDATION_ERROR", "$key debe ser un número de teléfono, por ejemplo +56 9 1234 5678.");
        return $v;
    }
    private function image(array $d, string $key, string $org): string
    {
        $url = Validator::string($d, $key, 0, 2048, "");
        if ($url === "") {
            return "";
        }
        if (!str_starts_with($url, "/")) {
            return Validator::https($url);
        }
        $id = basename($url);
        ApiError::require($url === $this->config["prefix"] . "/public/media/" . $id, 422, "INVALID_IMAGE", "Ruta de imagen inválida.");
        Support::record($this->db->one("SELECT id FROM media WHERE id=? AND organization_id=?", [$id, $org]));
        return $url;
    }
}
