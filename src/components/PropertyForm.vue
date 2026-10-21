<script setup>
import {ref,computed} from 'vue'
import {regions} from '../data/regions'
import {validateProperty,propertyTypes,describeDetails,cleanDetails,thumbUrl} from '../lib/domain'
import {prepareImage} from '../lib/image'
import {parseListingText} from '../lib/listing-import'
import {api} from '../lib/api'
import Icon from './Icon.vue'
const props=defineProps({initial:Object})
const emit=defineEmits(['save','close'])
const step=ref(1), errors=ref([]), busy=ref(''), photoUrl=ref('')
const draftKey='panorama-property-draft-v2'
let saved=null; try {saved=JSON.parse(localStorage.getItem(draftKey))} catch {}
if(saved?.id) saved=null
const blank={title:'',type:'Departamento',operation:'Venta',currency:'UF',price:'',region:'',commune:'',sector:'',address:'',area:'',beds:2,baths:1,images:[],description:'',status:'Borrador',details:{}}
const start={...blank,...(props.initial || saved || {})}
if(!start.images?.length&&start.image) start.images=[start.image]
const form=ref({...start,images:[...(start.images||[])],details:{...(start.details||{})}})
const amenitiesText=computed({get:()=>(form.value.details.amenities||[]).join('\n'),set:v=>{form.value.details.amenities=v.split('\n')}})
const regionName=computed(()=>regions.find(r=>r.id===form.value.region)?.name)
const extras=computed(()=>describeDetails(cleanDetails(form.value.details)))
function draft(){if(props.initial)return;try {localStorage.setItem(draftKey,JSON.stringify(form.value))} catch {} }
function save(status){errors.value=validateProperty(form.value);if(errors.value.length){step.value=1;return} emit('save',{...form.value,status,image:form.value.images[0]||'',price:Number(form.value.price),area:Number(form.value.area),beds:Number(form.value.beds),baths:Number(form.value.baths)});try{localStorage.removeItem(draftKey)}catch{}}
function addUrl(){const url=photoUrl.value.trim();if(!/^https:\/\//i.test(url)){errors.value=['La fotografía debe usar una dirección HTTPS.'];return}if(form.value.images.length>=20){errors.value=['Una propiedad admite hasta 20 fotografías.'];return}errors.value=[];form.value.images.push(url);photoUrl.value='';draft()}
function moveCover(i){const [url]=form.value.images.splice(i,1);form.value.images.unshift(url);draft()}
function removePhoto(i){form.value.images.splice(i,1);draft()}
// Sube fotos al servidor (se recodifican a WebP). Se detiene en el primer error para no perder el orden.
async function uploadPhotos(files){
 const room=20-form.value.images.length, list=[...files].slice(0,Math.max(0,room))
 if(files.length>list.length)errors.value=[`Solo se agregaron ${list.length} fotos: el máximo es 20 por propiedad.`]
 for(const [i,file] of list.entries()){busy.value=`Optimizando y subiendo foto ${i+1} de ${list.length}…`;try{form.value.images.push((await api.uploadImage(await prepareImage(file))).url)}catch(e){errors.value=[`No se pudo subir ${file.name}: ${e.message}`];break}}
 busy.value='';draft()
}
async function onPhotos(event){await uploadPhotos(event.target.files);event.target.value=''}
// Importa un aviso exportado: datos.txt + fotos. Los campos detectados reemplazan a los actuales.
async function importListing(event){
 const files=[...event.target.files];event.target.value=''
 const text=files.find(f=>/\.txt$/i.test(f.name)||f.type==='text/plain'), photos=files.filter(f=>f.type.startsWith('image/')).sort((a,b)=>a.name.localeCompare(b.name,'es',{numeric:true}))
 errors.value=[]
 if(text){const {details,...fields}=parseListingText(await text.text());Object.assign(form.value,fields);form.value.details={...form.value.details,...details}}
 if(photos.length)await uploadPhotos(photos)
 if(!text&&!photos.length)errors.value=['Selecciona el archivo datos.txt y las fotos del aviso.']
 draft()
}
</script>
<template><div class="modal-backdrop" @click.self="$emit('close')"><section class="modal wide" role="dialog" aria-modal="true" aria-labelledby="form-title" @keydown.esc="$emit('close')"><div class="modal-heading"><div><span class="eyebrow">TU CARTERA, AL DÍA</span><h2 id="form-title">{{initial ? 'Editar propiedad' : 'Una nueva oportunidad'}}</h2></div><button class="icon-button" aria-label="Cerrar formulario" @click="$emit('close')"><Icon name="close"/></button></div><div class="steps"><button v-for="(label,i) in ['Lo esencial','Fotos y descripción','Revisar y publicar']" :key="label" :class="{active:step===i+1}" @click="step=i+1">{{i+1}}. {{label}}</button></div>
<form @submit.prevent="step<3 ? step++ : save('Publicada')" @input="draft" @change="draft"><div v-if="errors.length" class="error" role="alert"><p v-for="e in errors" :key="e">{{e}}</p></div><p v-if="busy" class="notice" role="status">{{busy}}</p>
<div v-if="step===1" class="form-grid">
<label v-if="!initial" class="full import-box">Importar un aviso existente<input type="file" multiple accept=".txt,text/plain,image/*" :disabled="!!busy" @change="importListing"><small>Selecciona el archivo datos.txt y sus fotos. Completamos el formulario para que lo revises; la sección de contacto no se importa.</small></label>
<label class="full">Título del aviso<input v-model="form.title" required maxlength="120" placeholder="Ej. Departamento luminoso con terraza"></label><label>Operación<select v-model="form.operation"><option>Venta</option><option>Arriendo</option></select></label><label>Tipo<select v-model="form.type"><option v-for="t in propertyTypes" :key="t">{{t}}</option></select></label><label>Moneda<select v-model="form.currency"><option>UF</option><option>CLP</option></select></label><label>Precio<input v-model="form.price" type="number" required min="0.01" step="any"></label><label>Región<select v-model="form.region" required><option value="">Seleccionar región</option><option v-for="r in regions" :value="r.id" :key="r.id">{{r.name}}</option></select></label><label>Comuna<input v-model="form.commune" required placeholder="Ej. La Serena"><small>Ingreso manual; catálogo nacional pendiente de integración.</small></label><label>Sector o localidad<input v-model="form.sector"></label><label>Dirección privada<input v-model="form.address"><small>No se muestra en la ficha pública.</small></label><label>Superficie (m²)<input v-model="form.area" type="number" required min="0.01" step="any"></label><label>Dormitorios<input v-model="form.beds" type="number" min="0" max="99"></label><label>Baños<input v-model="form.baths" type="number" min="0" max="99"></label>
<fieldset class="full form-grid details-fields"><legend>Características <small>(opcionales)</small></legend><label>Superficie útil (m²)<input v-model="form.details.usefulArea" type="number" min="0.01" step="any"></label><label>Terraza (m²)<input v-model="form.details.terraceArea" type="number" min="0.01" step="any"></label><label>Estacionamientos<input v-model="form.details.parking" type="number" min="0" max="99"></label><label>Bodegas<input v-model="form.details.storage" type="number" min="0" max="99"></label><label>Piso<input v-model="form.details.floor" type="number" min="-10" max="300"></label><label>Año de construcción<input v-model="form.details.yearBuilt" type="number" min="1800" :max="new Date().getFullYear()+5"></label><label>Gastos comunes (CLP)<input v-model="form.details.commonExpenses" type="number" min="0" step="1"></label><label>Amoblado<select v-model="form.details.furnished"><option :value="undefined">Sin especificar</option><option>Sin amoblar</option><option>Semiamoblado</option><option>Amoblado</option></select></label><label>Mascotas<select v-model="form.details.pets"><option :value="undefined">Sin especificar</option><option>Sí</option><option>No</option><option>Consultar</option></select></label></fieldset></div>
<div v-if="step===2" class="form-grid"><div class="full photo-manager"><span class="field-label">Fotografías ({{form.images.length}} de 20)</span><ul v-if="form.images.length" class="photo-list"><li v-for="(url,i) in form.images" :key="url"><img :src="thumbUrl(url)" :alt="`Foto ${i+1}`" loading="lazy" decoding="async"><span v-if="i===0" class="photo-cover">Portada</span><div><button v-if="i>0" type="button" class="text-button" @click="moveCover(i)">Usar de portada</button><button type="button" class="text-button" :aria-label="`Quitar foto ${i+1}`" @click="removePhoto(i)">Quitar</button></div></li></ul><label class="button secondary upload-button">Subir fotos desde tu equipo<input type="file" multiple accept="image/*" :disabled="!!busy" @change="onPhotos"></label><small>JPG, PNG o WebP. Las fotos se optimizan antes de subirse; la primera es la portada.</small><div class="photo-url"><input v-model="photoUrl" type="url" placeholder="https://... (dirección de una foto)" aria-label="Dirección HTTPS de una foto" @keydown.enter.prevent="addUrl"><button type="button" class="button secondary" @click="addUrl">Agregar</button></div></div><label class="full">Equipamiento <small>(uno por línea)</small><textarea v-model="amenitiesText" rows="3" placeholder="Piscina&#10;Quincho"></textarea></label><label class="full">Descripción<textarea v-model="form.description" rows="5" maxlength="4000" placeholder="Cuenta qué hace especial este lugar"></textarea></label></div>
<div v-if="step===3" class="review"><span class="eyebrow">VISTA PREVIA</span><img v-if="form.images[0]" class="form-preview" :src="form.images[0]" alt="Portada de la propiedad"><h3>{{form.title || 'Tu próxima publicación'}}</h3><p>{{form.commune}} · {{regionName}}</p><p>{{form.area}} m² · {{form.beds}} dormitorios · {{form.baths}} baños · {{form.images.length}} fotos</p><p v-if="extras.length">{{extras.join(' · ')}}</p><p>{{form.currency}} {{form.price}}</p><p>{{form.description}}</p><div class="notice">Publicar hará visible esta propiedad en el portal y ocupará un cupo de tu plan.</div></div>
<div class="modal-actions"><button type="button" class="button secondary" :disabled="!!busy" @click="save('Borrador')">Guardar borrador</button><button class="button primary" type="submit" :disabled="!!busy">{{step<3 ? 'Continuar' : 'Publicar propiedad'}}<Icon name="arrow"/></button></div></form></section></div></template>
