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
 if (p.image && !/^https:\/\//i.test(p.image)) errors.push('La fotografía debe usar una dirección HTTPS.')
 return errors
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
