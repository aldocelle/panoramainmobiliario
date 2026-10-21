const photo = (id) => `https://images.unsplash.com/${id}?auto=format&fit=crop&w=1000&q=80`
export const seed = {
 properties: [
 {id:'PI-1001',title:'Luz, amplitud y una nueva perspectiva',type:'Departamento',operation:'Venta',currency:'UF',price:6850,region:'13',commune:'Providencia',sector:'Pedro de Valdivia',address:'Dirección de demostración',area:92,beds:3,baths:2,status:'Publicada',image:photo('photo-1600585154340-be6161a56a0c'),description:'Espacios luminosos, terraza y una ubicación conectada con la vida de barrio. Propiedad ficticia para explorar el portal.'},
 {id:'PI-1002',title:'Tu refugio frente al mar',type:'Departamento',operation:'Arriendo',currency:'CLP',price:790000,region:'05',commune:'Concón',sector:'Costa de Montemar',address:'Dirección de demostración',area:74,beds:2,baths:2,status:'Publicada',image:photo('photo-1600607687920-4e2a09cf159d'),description:'Una terraza para disfrutar el atardecer y ambientes pensados para vivir con calma. Propiedad ficticia.'},
 {id:'PI-1003',title:'La casa donde empieza otra historia',type:'Casa',operation:'Venta',currency:'UF',price:9200,region:'10',commune:'Puerto Varas',sector:'Sector residencial',address:'Dirección de demostración',area:148,beds:4,baths:3,status:'Publicada',image:photo('photo-1600047509807-ba8f99d2cdde'),description:'Jardín, espacios para compartir y una arquitectura acogedora en el sur de Chile. Propiedad ficticia.'},
 {id:'PI-1004',title:'Un espacio para vivir a tu ritmo',type:'Departamento',operation:'Arriendo',currency:'CLP',price:520000,region:'08',commune:'Concepción',sector:'Centro',address:'Dirección de demostración',area:58,beds:2,baths:1,status:'Borrador',image:photo('photo-1600566753086-00f18fb6b3ea'),description:'Cercanía a servicios y una distribución práctica. Propiedad ficticia.'}
 ],
 leads:[{id:'L-1',name:'Camila Torres',email:'camila@example.com',phone:'',propertyId:'PI-1001',stage:'Nuevo',note:'Datos ficticios. Quiere conocer orientación y gastos comunes.'},{id:'L-2',name:'Matías Rojas',email:'matias@example.com',phone:'',propertyId:'PI-1002',stage:'Contactado',note:'Datos ficticios. Prefiere una visita por la tarde.'}],
 visits:[],favorites:[]
}
