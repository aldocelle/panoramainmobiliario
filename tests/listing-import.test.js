import test from 'node:test'
import assert from 'node:assert/strict'
import {parseListingText,chileanNumber} from '../src/lib/listing-import.js'

const departamento = `Título: Departamento en venta en Calle Ficticia 100, Viña del Mar | UF 7.000
Ubicación: Viña del Mar, Calle Ficticia 100
Precio: UF 7.000,50
Habitaciones: 2
Baños: 2
Estacionamientos: 1
Gastos comunes: 240.000
Piso: 11
Año construcción: 1999

INFORMACIÓN DETALLADA
Superficie Útil 104 m²
Superficie Terraza 8 m²
Código Aviso 123
Tipo de publicación Venta
Tipo de propiedad Departamento

EQUIPAMIENTO
Exterior Piscina, quincho
Piso
Piso flotante en dormitorios.

DESCRIPCIÓN
Departamento luminoso.
Consulta al [Use el formulario de contacto]

CONTACTO
Persona — https://example.com/perfil`

test('números en formato chileno',()=>{assert.equal(chileanNumber('UF 7.000,00'),7000);assert.equal(chileanNumber('$ 105.000.000'),105000000);assert.equal(chileanNumber('104 m²'),104);assert.equal(chileanNumber('7.5'),7.5);assert.equal(chileanNumber('sin dato'),null)})
test('importa campos, características y descripción de un aviso',()=>{
 const r=parseListingText(departamento)
 assert.equal(r.title,'Departamento en venta en Calle Ficticia 100, Viña del Mar')
 assert.deepEqual([r.price,r.currency,r.commune,r.address,r.type,r.operation,r.beds,r.baths,r.area],[7000.5,'UF','Viña del Mar','Calle Ficticia 100','Departamento','Venta',2,2,104])
 assert.deepEqual(r.details,{usefulArea:104,terraceArea:8,parking:1,commonExpenses:240000,floor:11,yearBuilt:1999})
 assert.match(r.description,/^Departamento luminoso\.\n\nEquipamiento:\nExterior Piscina/)
 assert.doesNotMatch(r.description,/Use el formulario|example\.com|Persona/)
})
test('arriendo en pesos sin datos opcionales ni habitaciones en cero',()=>{
 const r=parseListingText('Título: Bodega en arriendo | $ 450.000\r\nUbicación: Valparaíso\r\nPrecio: $ 450.000\r\nHabitaciones: 0\r\n\r\nINFORMACIÓN DETALLADA\r\nSuperficie Construida 60 m²\r\nTipo de publicación Arriendo\r\nTipo de propiedad Bodega\r\n\r\nEQUIPAMIENTO\r\n-\r\n')
 assert.deepEqual([r.price,r.currency,r.commune,r.address,r.beds,r.operation,r.type,r.area,r.description],[450000,'CLP','Valparaíso',undefined,undefined,'Arriendo','Bodega',60,undefined])
 assert.deepEqual(r.details,{})
})
