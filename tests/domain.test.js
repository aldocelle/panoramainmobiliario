import test from 'node:test'
import assert from 'node:assert/strict'
import {filterProperties,validateProperty,hydrate,formatPrice} from '../src/lib/domain.js'
import {seed} from '../src/data/demo.js'
import {regions} from '../src/data/regions.js'
test('el portal público no muestra borradores ni pausadas',()=>{const properties=[...seed.properties,{...seed.properties[0],status:'Pausada'}];assert.equal(filterProperties(properties,{publicOnly:true}).length,3)})
test('búsqueda tolera tildes y combina región y operación',()=>{assert.equal(filterProperties(seed.properties,{query:'concon',region:'05',operation:'Arriendo'}).length,1);assert.equal(filterProperties(seed.properties,{query:'concon',region:'13'}).length,0)})
test('publicación rechaza precio inválido y ubicación incompleta',()=>{assert.equal(validateProperty(seed.properties[0]).length,0);assert.ok(validateProperty({...seed.properties[0],price:NaN,commune:''}).length>=2);assert.ok(validateProperty({...seed.properties[0],image:'javascript:alert(1)'}).length)})
test('recupera datos dañados sin bloquear el arranque',()=>{assert.deepEqual(hydrate('{bad',seed),seed);assert.deepEqual(hydrate(JSON.stringify({version:1,data:{properties:[]}}),seed),seed);assert.deepEqual(hydrate(JSON.stringify({version:1,data:seed}),{}),seed)})
test('códigos territoriales conservan ceros y son únicos',()=>{assert.equal(regions.length,16);assert.equal(new Set(regions.map(r=>r.id)).size,16);assert.ok(regions.some(r=>r.id==='01'))})
test('precio conserva moneda original sin inventar conversión UF',()=>{assert.match(formatPrice({currency:'UF',price:6850}),/^UF /);assert.match(formatPrice({currency:'CLP',price:790000}),/790/);assert.ok(!formatPrice({currency:'CLP',price:790000}).includes('UF'))})
