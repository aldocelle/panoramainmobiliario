<script setup>
// Editor de la página pública de la corredora (#corredora/{slug}); se guarda en la API.
import {ref,computed,onMounted} from 'vue'
import Icon from './Icon.vue'
import {api,PanoramaApiError} from '../lib/api'
import {regions} from '../data/regions'
import {communes,communesOf} from '../lib/communes'
import {prepareImage} from '../lib/image'
import {thumbUrl} from '../lib/domain'
const emit=defineEmits(['saved','notify'])
const form=ref(null),loadError=ref(''),errors=ref(''),busy=ref(''),saved=ref(false),zone=ref('')
onMounted(async()=>{try{form.value=await api.profile()}catch(e){loadError.value=e.status===404?'Tu página pública estará disponible cuando se actualice el servidor de Panorama.':e instanceof PanoramaApiError?e.message:'No se pudo cargar tu página.'}})
const officeCommunes=computed(()=>form.value?.officeRegion?communesOf(form.value.officeRegion):communes)
const pageUrl=computed(()=>form.value?new URL(`#corredora/${form.value.slug}`,location.href).href:'')
const shareUrl=computed(()=>form.value?new URL(`api/v1/public/broker-share/${form.value.slug}`,location.href).href:'')
function changed(){saved.value=false}
function slugify(){form.value.slug=form.value.slug.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+/,'').slice(0,60);changed()}
function addZone(){const name=zone.value.trim();if(name.length<2)return;if(!form.value.coverage.some(c=>c.toLowerCase()===name.toLowerCase())){if(form.value.coverage.length>=30){errors.value='Puedes declarar hasta 30 zonas.';return}form.value.coverage.push(name)}zone.value='';changed()}
function removeZone(name){form.value.coverage=form.value.coverage.filter(c=>c!==name);changed()}
async function upload(event,key){const file=event.target.files?.[0];event.target.value='';if(!file)return;busy.value=key==='coverUrl'?'Optimizando y subiendo la portada…':'Subiendo el logo…';errors.value='';try{form.value[key]=(await api.uploadImage(await prepareImage(file))).url;changed()}catch(e){errors.value=`No se pudo subir la imagen: ${e.message}`}finally{busy.value=''}}
async function save(){errors.value='';busy.value='Guardando…';try{form.value=await api.saveProfile({...form.value,slug:form.value.slug.replace(/-+$/,'')});saved.value=true;emit('saved',form.value);emit('notify','Tu página pública está actualizada.')}catch(e){errors.value=e instanceof PanoramaApiError?e.message:'No se pudo guardar. Revisa tu conexión.'}finally{busy.value=''}}
async function copy(){try{await navigator.clipboard.writeText(shareUrl.value);emit('notify','Enlace copiado. En WhatsApp se verá con tu portada.')}catch{emit('notify',shareUrl.value)}}
</script>
<template><section class="panel profile-editor" aria-labelledby="profile-editor-title">
<div class="panel-heading"><div><h3 id="profile-editor-title">Tu página pública</h3><p>Tu vitrina dentro de Panorama: portada, presentación, contacto y toda tu cartera publicada. Las cifras se calculan solas desde tus avisos.</p></div><a v-if="form" class="button secondary" :href="'#corredora/'+form.slug"><Icon name="external"/>Ver mi página</a></div>
<p v-if="loadError" class="error" role="alert">{{loadError}}</p>
<p v-else-if="!form" class="muted">Cargando tu página…</p>
<form v-else @submit.prevent="save">
 <div class="form-grid">
  <label class="full">Dirección de tu página<span class="slug-field"><span>…/#corredora/</span><input v-model="form.slug" required minlength="3" maxlength="60" autocapitalize="none" spellcheck="false" @input="slugify"></span><small>Solo minúsculas, números y guiones. Si la cambias, los enlaces que ya compartiste dejan de funcionar.</small></label>
  <label class="full">Frase destacada<input v-model="form.tagline" maxlength="140" placeholder="Ej. Corretaje en Viña del Mar y la costa desde 2004" @input="changed"></label>
  <label class="full">Presentación<textarea v-model="form.presentation" rows="5" maxlength="1500" placeholder="Quiénes son, cuánto tiempo llevan, en qué se especializan y cómo trabajan." @input="changed"></textarea><small>{{form.presentation.length}} / 1500</small></label>
 </div>
 <div class="profile-images">
  <div class="image-field"><span class="field-label">Portada</span><div class="image-preview cover"><img v-if="form.coverUrl" :src="thumbUrl(form.coverUrl)" alt="Portada actual"><span v-else>Sin portada: se usa la foto de tu aviso más reciente.</span></div><div class="image-actions"><label class="button secondary upload-button">Subir portada<input type="file" accept="image/*" :disabled="!!busy" @change="upload($event,'coverUrl')"></label><button v-if="form.coverUrl" type="button" class="text-button" @click="form.coverUrl='';changed()">Quitar</button></div><small>Horizontal, idealmente de 1920 × 1080 px.</small></div>
  <div class="image-field"><span class="field-label">Logo</span><div class="image-preview logo"><img v-if="form.logoUrl" :src="form.logoUrl" alt="Logo actual"><span v-else>Sin logo: se muestran tus iniciales.</span></div><div class="image-actions"><label class="button secondary upload-button">Subir logo<input type="file" accept="image/*" :disabled="!!busy" @change="upload($event,'logoUrl')"></label><button v-if="form.logoUrl" type="button" class="text-button" @click="form.logoUrl='';changed()">Quitar</button></div><small>Cuadrado, con fondo claro.</small></div>
 </div>
 <fieldset class="details-fields"><legend>Contacto público</legend><div class="form-grid">
  <label>WhatsApp<input v-model="form.whatsapp" type="tel" inputmode="tel" maxlength="30" placeholder="+56 9 1234 5678" @input="changed"></label>
  <label>Teléfono<input v-model="form.phone" type="tel" inputmode="tel" maxlength="30" placeholder="+56 32 123 4567" @input="changed"></label>
  <label>Correo<input v-model="form.email" type="email" maxlength="254" placeholder="contacto@tucorredora.cl" @input="changed"></label>
  <label>Sitio web<input v-model="form.website" type="url" maxlength="300" placeholder="https://tucorredora.cl" @input="changed"></label>
  <label class="full">Horario de atención<input v-model="form.hours" maxlength="140" placeholder="Lunes a viernes, 9:00 a 18:00" @input="changed"></label>
  <label>Región de la oficina<select v-model="form.officeRegion" @change="changed"><option value="">Sin oficina publicada</option><option v-for="r in regions" :key="r.id" :value="r.id">{{r.name}}</option></select></label>
  <label>Comuna de la oficina<input v-model="form.officeCommune" list="office-communes" maxlength="100" autocomplete="off" @input="changed"><datalist id="office-communes"><option v-for="c in officeCommunes" :key="c.code" :value="c.name"/></datalist></label>
 </div><small>Todo lo que escribas aquí será visible para cualquier persona en tu página.</small></fieldset>
 <fieldset class="details-fields"><legend>Zonas donde trabajas</legend>
  <div class="zone-input"><input v-model="zone" list="zone-communes" maxlength="100" autocomplete="off" placeholder="Escribe una comuna y presiona Agregar" aria-label="Agregar zona" @keydown.enter.prevent="addZone"><datalist id="zone-communes"><option v-for="c in communes" :key="c.code" :value="c.name"/></datalist><button type="button" class="button secondary" @click="addZone"><Icon name="plus"/>Agregar</button></div>
  <div v-if="form.coverage.length" class="filter-chips"><button v-for="c in form.coverage" :key="c" type="button" class="chip" :aria-label="'Quitar ' + c" @click="removeZone(c)">{{c}}<Icon name="close"/></button></div>
  <small>Se muestran como «declaradas por la corredora». Hasta 30 zonas.</small>
 </fieldset>
 <p v-if="errors" class="error" role="alert">{{errors}}</p>
 <div class="profile-save"><button class="button primary" type="submit" :disabled="!!busy">{{busy || 'Guardar página'}}<Icon v-if="!busy" name="check"/></button><span v-if="saved" class="saved-profile" role="status">Página actualizada.</span><button type="button" class="text-button" @click="copy">Copiar enlace para compartir<Icon name="share"/></button></div>
 <small class="profile-url">Tu página: <a :href="'#corredora/'+form.slug">{{pageUrl}}</a></small>
</form>
</section></template>
