import {normalize} from './domain.js'
// Sugerencias del buscador: comunas y sectores con avisos publicados (conteo real) y las 16 regiones.
// No sugiere comunas sin oferta: el catálogo nacional se integrará validado (ver docs/TERRITORIO.md).
export function buildPlaces(properties, regions) {
  const byKey = new Map()
  const add = (key, place) => { const found = byKey.get(key) || {...place, count: 0}; found.count++; byKey.set(key, found) }
  for (const p of properties) {
    const commune = p.commune?.trim(), sector = p.sector?.trim()
    if (p.status !== 'Publicada' || !commune) continue
    add('c:' + normalize(commune), {kind: 'commune', label: commune, detail: regions.find(r => r.id === p.region)?.name || '', commune})
    if (sector) add('s:' + normalize(commune) + ':' + normalize(sector), {kind: 'sector', label: sector, detail: commune, commune, sector})
  }
  return [...byKey.values(), ...regions.map(r => ({kind: 'region', label: r.name, detail: '', region: r.id, count: 0}))]
}
const rank = {commune: 0, sector: 1, region: 2}
export function suggestPlaces(places, text, limit = 6) {
  const q = normalize(text)
  if (q.length < 2) return []
  const score = name => name.startsWith(q) ? 0 : name.split(/[\s-]+/).some(w => w.startsWith(q)) ? 1 : name.includes(q) ? 2 : -1
  return places.map(place => ({place, score: score(normalize(place.label))})).filter(x => x.score >= 0)
    .sort((a, b) => a.score - b.score || rank[a.place.kind] - rank[b.place.kind] || b.place.count - a.place.count || a.place.label.localeCompare(b.place.label, 'es'))
    .slice(0, limit).map(x => x.place)
}
export const placeText = place => place.kind === 'sector' ? `${place.label}, ${place.commune}` : place.label
export function matchesPlace(property, place) {
  if (!place) return true
  if (place.kind === 'region') return property.region === place.region
  return normalize(property.commune) === normalize(place.commune) && (place.kind !== 'sector' || normalize(property.sector) === normalize(place.sector))
}
