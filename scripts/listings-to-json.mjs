// Convierte una carpeta de avisos exportados (una subcarpeta por aviso con datos.txt + fotos)
// en el JSON que consume backend/bin/import-listings.php.
// Uso: node scripts/listings-to-json.mjs <carpeta> [estado] > avisos.json
import { readdirSync, readFileSync, existsSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { parseListingText } from '../src/lib/listing-import.js'
import { cleanDetails } from '../src/lib/domain.js'

// Región por comuna. Solo comunas verificadas; una comuna desconocida detiene la conversión.
const REGION_BY_COMMUNE = {
  'Valparaíso': '05', 'Viña del Mar': '05', 'Quilpué': '05', 'Villa Alemana': '05', 'Concón': '05', 'Quintero': '05', 'Limache': '05',
  'Curacaví': '13', 'Santiago': '13',
}
const [dir, status = 'Publicada'] = process.argv.slice(2)
if (!dir) { console.error('Uso: node scripts/listings-to-json.mjs <carpeta> [estado]'); process.exit(1) }

const listings = []
for (const name of readdirSync(dir).sort()) {
  const folder = resolve(dir, name), text = join(folder, 'datos.txt')
  if (!existsSync(text)) continue
  const { details, ...f } = parseListingText(readFileSync(text, 'utf8'))
  const region = REGION_BY_COMMUNE[f.commune]
  if (!region) { console.error(`Comuna sin región conocida en ${name}: "${f.commune}"`); process.exit(1) }
  for (const k of ['title', 'price', 'currency', 'commune', 'type', 'operation', 'area'])
    if (f[k] == null) { console.error(`Falta ${k} en ${name}`); process.exit(1) }
  const photos = readdirSync(folder).filter(p => /\.(jpe?g|png|webp)$/i.test(p))
    .sort((a, b) => a.localeCompare(b, 'es', { numeric: true })).slice(0, 20).map(p => join(folder, p))
  listings.push({
    source: name, photos,
    property: { title: f.title, type: f.type, operation: f.operation, currency: f.currency, price: f.price, region, commune: f.commune, sector: '', privateAddress: f.address || '', area: f.area, beds: f.beds ?? 0, baths: f.baths ?? 0, description: f.description || '', images: [], status, details: cleanDetails(details) },
  })
}
process.stdout.write(JSON.stringify(listings, null, 1))
