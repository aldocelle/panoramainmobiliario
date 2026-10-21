// Oferta de Panorama en preparación. No son las tarifas de Portalinmobiliario.com.
// Precios en UF netas: null significa pendiente, nunca gratuito.
export const plans = [
 {id:'inicio',name:'Inicio',limit:5,monthlyUf:null,description:'Para comenzar a organizar tu cartera.'},
 {id:'profesional',name:'Profesional',limit:30,monthlyUf:null,description:'Para corredores con una cartera en crecimiento.',recommended:true},
 {id:'equipo',name:'Equipo',limit:100,monthlyUf:null,description:'Más capacidad para una operación de mayor volumen.'}
]
export const includedFeatures=['Tu marca y logo en cada publicación','Reporte descargable de publicaciones','Publicación de propiedades','Cartera con estados y borradores','Seguimiento de interesados','Agenda de visitas','Exportación de respaldo']
export const defaultPlanId='inicio'
export function getPlan(id){return plans.find(p=>p.id===id)||plans.find(p=>p.id===defaultPlanId)}
export function activeCount(properties){return properties.filter(p=>p.status==='Publicada').length}
export function canPublish(properties,candidate,plan){
 if(candidate.status!=='Publicada')return true
 const others=properties.filter(p=>p.id!==candidate.id)
 return activeCount(others)<plan.limit
}
export function canSelectPlan(properties,plan){return activeCount(properties)<=plan.limit}
// Aritmética comercial configurable. La tasa es un parámetro para evitar fijar
// una conclusión tributaria en el frontend. Se calcula en diezmilésimas de UF.
export function monthlyQuote(plan,taxRate){
 if(plan.monthlyUf===null)return null
 if(!Number.isFinite(plan.monthlyUf)||plan.monthlyUf<=0||!Number.isFinite(taxRate)||taxRate<0)throw new Error('Tarifa o tasa inválida')
 const net=Math.round(plan.monthlyUf*10000)
 const tax=Math.round(net*taxRate)
 return {net:net/10000,tax:tax/10000,total:(net+tax)/10000}
}
