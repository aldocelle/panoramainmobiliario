import catalog from '../data/communes.json' with {type:'json'}
import {normalize} from './domain.js'
// Catálogo nacional de 346 comunas con CUT (fuente y validaciones en el propio JSON y en docs/TERRITORIO.md).
export const communes=catalog.records
export const communesOf=region=>communes.filter(c=>c.region===region)
// Clave tolerante a tildes, espacios y apóstrofos: "Tiltil" = "Til Til", "OHiggins" = "O'Higgins".
export const placeKey=value=>normalize(value).replace(/[\s'’.-]/g,'')
export function findCommune(name,region){const key=placeKey(name);return key?communes.find(c=>placeKey(c.name)===key&&(!region||c.region===region))||null:null}
