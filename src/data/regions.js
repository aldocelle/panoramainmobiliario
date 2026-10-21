// Catálogo de regiones. Códigos territoriales almacenados como texto.
// Comunas: catálogo nacional con CUT en src/data/communes.json (ver docs/TERRITORIO.md).
export const regions = [
 ['15','Arica y Parinacota'],['01','Tarapacá'],['02','Antofagasta'],['03','Atacama'],
 ['04','Coquimbo'],['05','Valparaíso'],['13','Metropolitana de Santiago'],
 ['06',"Libertador General Bernardo O’Higgins"],['07','Maule'],['16','Ñuble'],
 ['08','Biobío'],['09','La Araucanía'],['14','Los Ríos'],['10','Los Lagos'],
 ['11','Aysén del General Carlos Ibáñez del Campo'],['12','Magallanes y de la Antártica Chilena']
].map(([id,name])=>({id,name}))
