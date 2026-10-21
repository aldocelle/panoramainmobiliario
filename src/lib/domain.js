export const stages = ['Nuevo','Contactado','Visita agendada','En negociación','Cerrado']
export const statuses = ['Borrador','Publicada','Pausada','Vendida','Arrendada']
export const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim()
export function filterProperties(properties, filters = {}) {
 return properties.filter(p => (!filters.publicOnly || p.status === 'Publicada') && (!filters.operation || p.operation === filters.operation) && (!filters.region || p.region === filters.region) && (!filters.type || p.type === filters.type) && (!filters.status || p.status === filters.status) && (!filters.query || normalize([p.title,p.commune,p.sector,p.id].join(' ')).includes(normalize(filters.query))))
}
export function validateProperty(p) {
 const errors = []
 if (!p.title?.trim()) errors.push('Escribe un título para la propiedad.')
 if (!p.region || !p.commune?.trim()) errors.push('Selecciona una región y escribe la comuna.')
 if (!Number.isFinite(Number(p.price)) || Number(p.price) <= 0) errors.push('El precio debe ser mayor a cero.')
 if (!Number.isFinite(Number(p.area)) || Number(p.area) <= 0) errors.push('La superficie debe ser mayor a cero.')
 if ([p.image,...(p.images||[])].filter(Boolean).some(u => !/^https:\/\//i.test(u) && !isMediaPath(u))) errors.push('Las fotografías deben usar una dirección HTTPS o subirse desde tu equipo.')
 return errors
}
export const propertyTypes = ['Departamento','Casa','Terreno','Oficina','Local comercial','Parcela','Bodega']
export const isMediaPath = url => /^\/api\/v1\/public\/media\/[0-9a-f-]{36}$/i.test(url)
// Características opcionales: se envían solo las que tienen dato.
const detailKeys = ['parking','storage','usefulArea','terraceArea','commonExpenses','floor','yearBuilt','furnished','pets']
export function cleanDetails(d = {}) {
 const out = {}
 for (const k of detailKeys) if (d[k] !== '' && d[k] != null) out[k] = ['furnished','pets'].includes(k) ? d[k] : Number(d[k])
 const amenities = (d.amenities || []).map(a => String(a).trim()).filter(Boolean)
 if (amenities.length) out.amenities = amenities
 return out
}
export function describeDetails(d = {}) {
 const n = v => Number(v).toLocaleString('es-CL')
 return [
  d.usefulArea && `Superficie útil ${n(d.usefulArea)} m²`, d.terraceArea && `Terraza ${n(d.terraceArea)} m²`,
  d.parking != null && `${d.parking} ${d.parking === 1 ? 'estacionamiento' : 'estacionamientos'}`, d.storage != null && `${d.storage} ${d.storage === 1 ? 'bodega' : 'bodegas'}`,
  d.floor != null && `Piso ${d.floor}`, d.yearBuilt && `Construcción ${d.yearBuilt}`,
  d.commonExpenses && `Gastos comunes $${n(d.commonExpenses)}`, d.furnished, d.pets && `Mascotas: ${d.pets}`,
  ...(d.amenities || []),
 ].filter(Boolean)
}
export const formatPrice = p => p.currency === 'UF' ? `UF ${Number(p.price).toLocaleString('es-CL',{maximumFractionDigits:2})}` : new Intl.NumberFormat('es-CL',{style:'currency',currency:'CLP',maximumFractionDigits:0}).format(p.price)
export function hydrate(raw, fallback) {
 try { const value = JSON.parse(raw); if(value?.version !== 1 || !value.data || !['properties','leads','visits','favorites'].every(k => Array.isArray(value.data[k]))) return structuredClone(fallback); return value.data } catch { return structuredClone(fallback) }
}

// Identificadores locales; también funciona en vistas previas HTTP.
export function createId() {
 if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()
 const bytes = new Uint8Array(16)
 globalThis.crypto.getRandomValues(bytes)
 return Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('')
}

// Traducción entre el formato de la API (images[], privateAddress) y el de las vistas (image, address).
export function fromApi(p) {
 const { images = [], privateAddress = '', ...rest } = p
 return { ...rest, details: { ...(rest.details || {}) }, image: images[0] || '', images, address: privateAddress }
}
export function toApi(p) {
 const body = { title: p.title, type: p.type, operation: p.operation, currency: p.currency, price: Number(p.price), region: p.region, commune: p.commune, sector: p.sector || '', privateAddress: p.address || '', area: Number(p.area), beds: Number(p.beds), baths: Number(p.baths), description: p.description || '', images: (p.images?.length ? p.images : [p.image]).filter(Boolean), status: p.status, details: cleanDetails(p.details) }
 if (p.id) Object.assign(body, { id: p.id, version: p.version })
 return body
}

// Interesados y visitas: la API usa snake_case y horarios en segundos UTC.
export const leadFromApi = l => ({ id: l.id, propertyId: l.property_id, name: l.name, email: l.email, phone: l.phone, note: l.note, stage: l.stage, version: l.version, createdAt: l.created_at })
export const visitFromApi = v => ({ id: v.id, propertyId: v.property_id, leadId: v.lead_id, agentId: v.agent_id, start: v.starts_at * 1000, end: v.ends_at * 1000, note: v.note, status: v.status, version: v.version })
const isoSeconds = ms => new Date(ms).toISOString().replace(/\.\d{3}Z$/, 'Z')
// date y time vienen de inputs locales del navegador; la API recibe el instante con zona explícita.
export function visitToApi({ propertyId, leadId, date, time, duration = 60, note = '' }, agentId) {
 const start = new Date(`${date}T${time}`).getTime()
 if (!Number.isFinite(start)) return null
 return { propertyId, leadId, agentId, startsAt: isoSeconds(start), endsAt: isoSeconds(start + Number(duration) * 60000), note }
}
