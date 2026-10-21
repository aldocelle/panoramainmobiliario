import test from 'node:test'
import assert from 'node:assert/strict'
import {referenceProjectDiscount} from '../src/data/developer-plans.js'
test('matriz referencia respeta bordes de volumen y destacados',()=>{assert.equal(referenceProjectDiscount(16,0).percent,5);assert.equal(referenceProjectDiscount(30,0).percent,10);assert.equal(referenceProjectDiscount(5,3).percent,5);assert.equal(referenceProjectDiscount(15,6).percent,10);assert.equal(referenceProjectDiscount(29,16).percent,15);assert.equal(referenceProjectDiscount(30,16).percent,20)})
test('guiones no se convierten en cero ni se permiten cantidades imposibles',()=>{assert.deepEqual(referenceProjectDiscount(5,0),{valid:true,percent:null});for(const [total,featured] of [[0,0],[5,6],[30,-1],[1.5,0],[30,2.5],[NaN,1]])assert.equal(referenceProjectDiscount(total,featured).valid,false)})
