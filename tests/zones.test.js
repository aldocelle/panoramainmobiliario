import test from 'node:test'
import assert from 'node:assert/strict'
import {referenceZones,findReferenceZone,searchReferenceZones} from '../src/lib/zones.js'
test('transcripción contiene 153 filas únicas y conserva el agrupamiento comercial',()=>{assert.equal(referenceZones.length,153);assert.equal(new Set(referenceZones.map(r=>r.group+':'+r.commune)).size,153);assert.deepEqual(Object.fromEntries(['Norte','Quinta región','Región metropolitana','Sur'].map(g=>[g,referenceZones.filter(r=>r.group===g).length])),{'Norte':16,'Quinta región':27,'Región metropolitana':43,'Sur':67})})
test('consulta tolera tildes y alias sin alterar transcripción',()=>{assert.equal(findReferenceZone('Vina del Mar').zone,1);assert.equal(findReferenceZone('La Calera').commune,'Calera');assert.equal(findReferenceZone('Pedro Aguirre Cerda').commune,'Pedro Aguirre C.');assert.equal(findReferenceZone('Las Cabras').zone,3)})
test('ausencia de comuna no se transforma en zona comercial por defecto',()=>{assert.equal(findReferenceZone('Comuna no incluida'),null);assert.equal(findReferenceZone(''),null)})
test('filtros combinan comuna, grupo y zona',()=>{assert.equal(searchReferenceZones({query:'concon',group:'Quinta región',zone:'1'}).length,1);assert.equal(searchReferenceZones({query:'concon',zone:'3'}).length,0)})
