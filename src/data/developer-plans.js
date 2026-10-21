// Matriz transcrita de captura atribuida a Portalinmobiliario.com.
// Referencia externa: no es una promoción activa de Panorama.
export const referenceDiscounts={
 columns:['1–5 proyectos','6–15 proyectos','16–29 proyectos','30+ proyectos'],
 rows:[
 {label:'0–2 destacados',values:[null,null,5,10]},
 {label:'3–5 destacados',values:[5,5,10,15]},
 {label:'6–15 destacados',values:[null,10,10,15]},
 {label:'16+ destacados',values:[null,null,15,20]}
 ]
}
export function referenceProjectDiscount(total,featured){
 if(!Number.isInteger(total)||total<1||!Number.isInteger(featured)||featured<0||featured>total)return {valid:false,percent:null}
 const column=total<=5?0:total<=15?1:total<=29?2:3
 const row=featured<=2?0:featured<=5?1:featured<=15?2:3
 return {valid:true,percent:referenceDiscounts.rows[row].values[column]}
}
export const developerTiers=[
 {id:'esencial',name:'Esencial',description:'Ficha del proyecto y sus tipologías. Publicación base propuesta.'},
 {id:'destacado',name:'Destacado',description:'Mayor exposición del proyecto, con espacios patrocinados identificados.'},
 {id:'proyeccion',name:'Proyección',description:'Exposición ampliada y presencia de marca para campañas de proyectos.'}
]
