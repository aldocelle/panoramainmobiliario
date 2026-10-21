import reference from '../data/reference-zones.json' with {type:'json'}
import {normalize} from './domain.js'
export const referenceZones=reference.records
export const zoneGroups=[...new Set(referenceZones.map(r=>r.group))]
const aliases={'la calera':'calera','pedro aguirre cerda':'pedro aguirre c.','puerto natales':'natales','las cabras':'las cabras-l. rapel','tiltil':'til til'}
const key=value=>aliases[normalize(value)]||normalize(value)
export function findReferenceZone(commune){return referenceZones.find(r=>key(r.commune)===key(commune))||null}
export function searchReferenceZones({query='',group='',zone=''}={}){return referenceZones.filter(r=>(!query||key(r.commune).includes(key(query)))&&(!group||r.group===group)&&(!zone||r.zone===Number(zone)))}
