import {normalize} from './domain.js'
import {placeKey} from './communes.js'
// Sugerencias del buscador: comunas y sectores con avisos publicados (conteo real), el resto de las comunas
// del catálogo nacional (sin avisos, conteo 0) y las 16 regiones.
export function buildPlaces(properties, regions, communes = []) {
  const byKey = new Map()
  const add = (key, place) => { const found = byKey.get(key) || {...place, count: 0}; found.count++; byKey.set(key, found) }
  for (const p of properties) {
    const commune = p.commune?.trim(), sector = p.sector?.trim()
    if (p.status !== 'Publicada' || !commune) continue
    add('c:' + placeKey(commune), {kind: 'commune', label: commune, detail: regions.find(r => r.id === p.region)?.name || '', commune})
    if (sector) add('s:' + placeKey(commune) + ':' + placeKey(sector), {kind: 'sector', label: sector, detail: commune, commune, sector})
  }
  for (const c of communes) if (!byKey.has('c:' + placeKey(c.name))) byKey.set('c:' + placeKey(c.name), {kind: 'commune', label: c.name, detail: regions.find(r => r.id === c.region)?.name || '', commune: c.name, count: 0})
  return [...byKey.values(), ...regions.map(r => ({kind: 'region', label: r.name, detail: '', region: r.id, count: 0}))]
}
const rank = {commune: 0, sector: 1, region: 2}
export function suggestPlaces(places, text, limit = 6) {
  const q = normalize(text), qKey = placeKey(text)
  if (q.length < 2) return []
  const score = label => { const name = normalize(label); return name.startsWith(q) || placeKey(label).startsWith(qKey) ? 0 : name.split(/[\s-]+/).some(w => w.startsWith(q)) ? 1 : name.includes(q) ? 2 : -1 }
  return places.map(place => ({place, score: score(place.label)})).filter(x => x.score >= 0)
    .sort((a, b) => a.score - b.score || rank[a.place.kind] - rank[b.place.kind] || b.place.count - a.place.count || a.place.label.localeCompare(b.place.label, 'es'))
    .slice(0, limit).map(x => x.place)
}
export const placeText = place => place.kind === 'sector' ? `${place.label}, ${place.commune}` : place.label
export function matchesPlace(property, place) {
  if (!place) return true
  if (place.kind === 'region') return property.region === place.region
  return placeKey(property.commune) === placeKey(place.commune) && (place.kind !== 'sector' || placeKey(property.sector) === placeKey(place.sector))
}
