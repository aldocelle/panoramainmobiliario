// Lee el texto de un aviso exportado ("datos.txt": Título, Precio, INFORMACIÓN DETALLADA, DESCRIPCIÓN...)
// y devuelve campos para precargar el formulario. Rescatado del importador de PropiedadesMar.
// La sección CONTACTO se descarta: son datos personales de terceros.
const TYPES = ['Departamento', 'Casa', 'Terreno', 'Oficina', 'Local comercial', 'Parcela', 'Bodega']

// Formato chileno: punto de miles y coma decimal ("7.000,00", "105.000.000").
export function chileanNumber(text) {
  const raw = String(text ?? '').match(/\d[\d.,]*/)?.[0]
  if (!raw) return null
  const value = Number(raw.replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.'))
  return Number.isFinite(value) ? value : null
}

function section(text, name, next) {
  const start = text.search(new RegExp(`^${name}\\s*$`, 'mi'))
  if (start < 0) return ''
  const body = text.slice(start).replace(/^.*\n?/, '')
  const end = body.search(new RegExp(`^(${next.join('|')})\\s*$`, 'mi'))
  return (end < 0 ? body : body.slice(0, end)).trim()
}

const field = (text, label) => text.match(new RegExp(`^${label}[ \\t]*:?[ \\t]*(.+)$`, 'mi'))?.[1].trim() ?? ''

export function parseListingText(text) {
  text = String(text ?? '').replace(/\r\n?/g, '\n')
  const headings = ['INFORMACIÓN DETALLADA', 'INFORMACION DETALLADA', 'EQUIPAMIENTO', 'DESCRIPCIÓN', 'DESCRIPCION', 'CONTACTO']
  const out = { details: {} }

  const title = field(text, 'Título').replace(/\s*\|\s*(UF|\$).*$/i, '')
  if (title) out.title = title.slice(0, 120)

  const price = field(text, 'Precio')
  const amount = chileanNumber(price)
  if (amount) Object.assign(out, { price: amount, currency: /UF/i.test(price) ? 'UF' : 'CLP' })

  // "Comuna, resto": el resto suele ser calle y número, por eso va a la dirección privada.
  const [commune, ...rest] = field(text, 'Ubicación').split(',').map(s => s.trim()).filter(Boolean)
  if (commune) out.commune = commune
  if (rest.length) out.address = rest.join(', ')

  const integer = label => { const v = chileanNumber(field(text, label)); return v == null ? null : Math.round(v) }
  const beds = integer('Habitaciones'), baths = integer('Baños')
  if (beds > 0) out.beds = beds
  if (baths > 0) out.baths = baths

  const type = field(text, 'Tipo de propiedad')
  const match = TYPES.find(t => t.toLowerCase() === type.toLowerCase())
  if (match) out.type = match
  const operation = field(text, 'Tipo de publicación')
  if (/arriendo/i.test(operation)) out.operation = 'Arriendo'
  else if (/venta/i.test(operation)) out.operation = 'Venta'

  const surface = name => chileanNumber(text.match(new RegExp(`^Superficie ${name}[ \\t]*:?[ \\t]*([\\d.,]+)`, 'mi'))?.[1])
  const built = surface('(?:Construida|Total)'), useful = surface('Útil'), terrace = surface('Terraza')
  if (built || useful) out.area = built || useful
  const d = out.details
  if (useful) d.usefulArea = useful
  if (terrace) d.terraceArea = terrace
  const parking = integer('Estacionamientos'); if (parking != null) d.parking = parking
  const expenses = integer('Gastos comunes'); if (expenses) d.commonExpenses = expenses
  const floor = integer('Piso'); if (floor != null) d.floor = floor
  const year = integer('Año construcción'); if (year >= 1800) d.yearBuilt = year

  const description = section(text, 'DESCRIPCI[ÓO]N', headings).split('\n').filter(l => !/^\s*\[.*\]\s*$|\[Use el formulario/i.test(l)).join('\n').trim()
  const equipment = section(text, 'EQUIPAMIENTO', headings)
  const parts = [description, equipment && equipment !== '-' ? `Equipamiento:\n${equipment}` : ''].filter(Boolean)
  if (parts.length) out.description = parts.join('\n\n').slice(0, 4000)
  return out
}
