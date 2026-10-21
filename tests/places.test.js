import test from 'node:test'
import assert from 'node:assert/strict'
import {buildPlaces,suggestPlaces,placeText,matchesPlace} from '../src/lib/places.js'
const regions=[{id:'05',name:'Valparaíso'},{id:'13',name:'Metropolitana de Santiago'}]
const props=[
 {status:'Publicada',commune:'Viña del Mar',sector:'Reñaca',region:'05'},
 {status:'Publicada',commune:'Viña del Mar',sector:'',region:'05'},
 {status:'Publicada',commune:'Valparaíso',sector:'Cerro Alegre',region:'05'},
 {status:'Borrador',commune:'Pudahuel',sector:'',region:'13'}]
const places=buildPlaces(props,regions)
test('solo sugiere lugares con avisos publicados y cuenta los reales',()=>{assert.equal(places.find(p=>p.label==='Viña del Mar').count,2);assert.equal(places.find(p=>p.label==='Reñaca').count,1);assert.equal(places.some(p=>p.label==='Pudahuel'),false)})
test('tolera tildes, prioriza comuna sobre sector y región, y exige dos letras',()=>{assert.deepEqual(suggestPlaces(places,'vina').map(p=>p.label),['Viña del Mar']);assert.deepEqual(suggestPlaces(places,'valpa').map(p=>p.kind),['commune','region']);assert.deepEqual(suggestPlaces(places,'v'),[]);assert.deepEqual(suggestPlaces(places,'alegre').map(p=>p.label),['Cerro Alegre'])})
test('el filtro por lugar distingue comuna, sector y región',()=>{const sector=places.find(p=>p.label==='Reñaca');assert.equal(placeText(sector),'Reñaca, Viña del Mar');assert.equal(matchesPlace(props[0],sector),true);assert.equal(matchesPlace(props[1],sector),false);assert.equal(matchesPlace(props[1],places.find(p=>p.label==='Viña del Mar')),true);assert.equal(matchesPlace(props[3],places.find(p=>p.kind==='region'&&p.region==='13')),true);assert.equal(matchesPlace(props[0],null),true)})
