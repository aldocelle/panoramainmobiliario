<?php
declare(strict_types=1);
// Convierte la base SQLite del sitio de Propiedades Mar (properties + property_images) al JSON que
// consume backend/bin/import-listings.php. Las fotos se toman de <uploads>/<archivo> en este equipo.
// Uso: php scripts/propiedadesmar-db-to-json.php <propiedadesmar.db> <carpeta-uploads> > avisos.json
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
[$_, $dbPath, $uploads] = $argv + [null, null, null];
if (!is_file((string) $dbPath) || !is_dir((string) $uploads)) {
    fwrite(STDERR, "Uso: php scripts/propiedadesmar-db-to-json.php <propiedadesmar.db> <carpeta-uploads>\n");
    exit(1);
}
$regions = ["Valparaíso" => "05", "Viña del Mar" => "05", "Quilpué" => "05", "Villa Alemana" => "05", "Concón" => "05", "Quintero" => "05", "Limache" => "05", "Curacaví" => "13"];
$types = ["Departamentos" => "Departamento", "Casas" => "Casa", "Oficinas" => "Oficina", "Terrenos" => "Terreno", "Parcelas" => "Parcela", "Bodegas" => "Bodega", "Locales comerciales" => "Local comercial"];
$furnished = ["Sin amoblar" => "Sin amoblar", "Semi amoblado" => "Semiamoblado", "Semiamoblado" => "Semiamoblado", "Amoblado" => "Amoblado"];
$pets = ["Sí" => "Sí", "Si" => "Sí", "No" => "No", "Consultar" => "Consultar"];
// Códigos de equipamiento del sitio original → texto con tildes; un código nuevo se muestra legible igual.
$amenityLabels = [
    "alarma" => "Alarma", "area_juegos_infantiles" => "Área de juegos infantiles", "area_verde" => "Área verde", "ascensor" => "Ascensor",
    "azotea" => "Azotea", "balcon" => "Balcón", "camaras_seguridad" => "Cámaras de seguridad", "canchas_multiples" => "Canchas múltiples",
    "citofono" => "Citófono", "control_acceso" => "Control de acceso", "cowork" => "Cowork", "estacionamiento_bicicletas" => "Estacionamiento de bicicletas",
    "estacionamiento_visitas" => "Estacionamiento de visitas", "gas_natural" => "Gas natural", "generador" => "Generador", "gimnasio" => "Gimnasio",
    "guardia_24h" => "Guardia 24 h", "jardin_comun" => "Jardín común", "lavanderia" => "Lavandería", "piscina" => "Piscina", "portero" => "Portero",
    "quincho" => "Quincho", "rampa_discapacidad" => "Rampa de accesibilidad", "recepcion" => "Recepción", "sala_reuniones" => "Sala de reuniones",
    "salon_fiestas" => "Salón de eventos", "terraza_comun" => "Terraza común", "terraza_privada" => "Terraza privada",
];
$label = fn(string $s) => $amenityLabels[$s] ?? mb_strtoupper(mb_substr($t = str_replace("_", " ", $s), 0, 1)) . mb_substr($t, 1);
$num = fn($v) => $v === null || $v === "" ? null : (float) $v;
$pdo = new PDO("sqlite:$dbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$images = $pdo->prepare("SELECT url FROM property_images WHERE property_id=? ORDER BY sort_order, created_at LIMIT 20");
$out = [];
foreach ($pdo->query("SELECT * FROM properties WHERE published=1 ORDER BY created_at") as $p) {
    $commune = trim((string) $p["city"]);
    $type = $types[trim((string) $p["property_type"])] ?? null;
    if (!isset($regions[$commune]) || !$type) {
        fwrite(STDERR, "Sin región o tipo conocido: {$p["title"]} ({$commune}, {$p["property_type"]})\n");
        exit(1);
    }
    $photos = [];
    $images->execute([$p["id"]]);
    foreach ($images->fetchAll(PDO::FETCH_COLUMN) as $url) {
        $file = rtrim($uploads, "/") . "/" . basename(parse_url($url, PHP_URL_PATH));
        if (is_file($file)) {
            $photos[] = $file;
        }
    }
    $details = array_filter([
        "parking" => $p["estacionamientos"] !== null ? (int) $p["estacionamientos"] : null,
        "storage" => $p["bodegas_count"] !== null ? (int) $p["bodegas_count"] : null,
        "usefulArea" => $num($p["superficie_util"]) ?: null,
        "terraceArea" => $num($p["superficie_terraza"]) ?: null,
        "commonExpenses" => $num($p["gastos_comunes"]) ? (int) round((float) $p["gastos_comunes"]) : null,
        "floor" => $p["numero_piso"] !== null ? (int) $p["numero_piso"] : null,
        // "antigüedad" se registró en años al cargar el aviso: se convierte a año de construcción.
        "yearBuilt" => ($a = (int) $p["antiguedad"]) > 0 && $a < 200 ? (int) substr((string) $p["created_at"], 0, 4) - $a : null,
        "furnished" => $furnished[(string) $p["amoblado"]] ?? null,
        "pets" => $pets[(string) $p["mascotas"]] ?? null,
        "amenities" => array_slice(array_map($label, json_decode((string) $p["amenities"], true) ?: []), 0, 40) ?: null,
    ], fn($v) => $v !== null);
    $area = $num($p["area_sq_m"]) ?: $num($p["superficie_util"]);
    $out[] = [
        "source" => $p["slug"],
        "photos" => $photos,
        "property" => [
            "title" => mb_substr(trim((string) $p["title"]), 0, 120),
            "type" => $type,
            "operation" => $p["operation_type"] === "Arriendo" ? "Arriendo" : "Venta",
            "currency" => $p["price_type"] === "UF" ? "UF" : "CLP",
            "price" => (float) $p["price"],
            "region" => $regions[$commune],
            "commune" => $commune,
            "sector" => "",
            "privateAddress" => mb_substr(trim((string) $p["address"]), 0, 250),
            "area" => $area,
            "beds" => (int) $p["bedrooms"],
            "baths" => (int) $p["bathrooms"],
            "description" => mb_substr(trim((string) $p["description"]), 0, 6000),
            "images" => [],
            "status" => "Publicada",
            "details" => (object) $details,
        ],
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
