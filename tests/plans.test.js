import test from 'node:test'
import assert from 'node:assert/strict'
import {plans,getPlan,activeCount,canPublish,canSelectPlan,monthlyQuote} from '../src/data/plans.js'
const plan={limit:2},properties=[{id:'a',status:'Publicada'},{id:'b',status:'Publicada'},{id:'c',status:'Borrador'}]
test('solo las publicaciones activas ocupan cupos',()=>{assert.equal(activeCount([...properties,{id:'d',status:'Pausada'},{id:'e',status:'Vendida'},{id:'f',status:'Arrendada'}]),2)})
test('al llenar cupos bloquea nuevo aviso pero permite editar y guardar borradores',()=>{assert.equal(canPublish(properties,{id:'new',status:'Publicada'},plan),false);assert.equal(canPublish(properties,{id:'a',status:'Publicada'},plan),true);assert.equal(canPublish(properties,{id:'new',status:'Borrador'},plan),true);assert.equal(canPublish(properties,{id:'c',status:'Publicada'},plan),false)})
test('pausar libera capacidad y republicar consume un cupo',()=>{const paused=properties.map(p=>p.id==='a'?{...p,status:'Pausada'}:p);assert.equal(canPublish(paused,{id:'c',status:'Publicada'},plan),true)})
test('reducir capacidad no borra ni despublica propiedades',()=>{assert.equal(canSelectPlan(properties,{limit:1}),false);assert.equal(canSelectPlan(properties,{limit:2}),true);assert.equal(activeCount(properties),2)})
test('las tarifas pendientes no se muestran como gratis',()=>{for(const p of plans){assert.equal(monthlyQuote(p,0.19),null)}assert.equal(getPlan('unknown').id,'inicio')})
test('cálculo configurable separa neto, impuestos y total sin inventar conversión a pesos',()=>{assert.deepEqual(monthlyQuote({monthlyUf:1.11},0.19),{net:1.11,tax:0.2109,total:1.3209});assert.throws(()=>monthlyQuote({monthlyUf:-1},0.19));assert.throws(()=>monthlyQuote({monthlyUf:1},NaN))})
