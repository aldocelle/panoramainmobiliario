<script setup>
// Página pública de una corredora (#corredora/{slug}): portada, datos reales, presentación, contacto y cartera.
import {ref,computed,watch,onUnmounted} from 'vue'
import Icon from './Icon.vue'
import PropertyCard from './PropertyCard.vue'
import {api} from '../lib/api'
import {regions} from '../data/regions'
import heroFallback from '../assets/hero-1920.webp'
import logoLight from '../assets/logo-panorama-light.png'
const props=defineProps({slug:String,properties:Array,favorites:Array})
const emit=defineEmits(['open','favorite','navigate','notify'])
const profile=ref(null),missing=ref(false),operation=ref(''),type=ref(''),commune=ref('')
const originalTitle=document.title
async function load(){profile.value=null;missing.value=false;operation.value='';type.value='';commune.value='';try{profile.value=await api.brokerPage(props.slug);document.title=`${profile.value.name} · Panorama Inmobiliario`}catch{missing.value=true}}
watch(()=>props.slug,load,{immediate:true})
onUnmounted(()=>{document.title=originalTitle})
const stats=computed(()=>profile.value?.stats||{published:0,sale:0,rent:0,communes:[]})
const mine=computed(()=>props.properties.filter(p=>p.brand?.slug===props.slug))
const types=computed(()=>[...new Set(mine.value.map(p=>p.type))].sort())
const shown=computed(()=>mine.value.filter(p=>(!operation.value||p.operation===operation.value)&&(!type.value||p.type===type.value)&&(!commune.value||p.commune===commune.value)))
const cover=computed(()=>profile.value?.coverUrl||profile.value?.stats.firstImage||heroFallback)
const since=computed(()=>profile.value?new Intl.DateTimeFormat('es-CL',{month:'long',year:'numeric'}).format(new Date(profile.value.joinedAt)):'')
const office=computed(()=>[profile.value?.officeCommune,regions.find(r=>r.id===profile.value?.officeRegion)?.name].filter(Boolean).join(', '))
const initials=computed(()=>(profile.value?.name||'').split(/\s+/).map(w=>w[0]).join('').slice(0,2).toUpperCase())
const whatsappUrl=computed(()=>profile.value?.whatsapp?`https://wa.me/${profile.value.whatsapp.replace(/\D/g,'')}?text=${encodeURIComponent('Hola, vi su página en Panorama Inmobiliario.')}`:'')
const website=computed(()=>profile.value?.website?.replace(/^https:\/\/(www\.)?/,'').replace(/\/$/,''))
const hasContact=computed(()=>['phone','whatsapp','email','website','hours'].some(k=>profile.value?.[k])||office.value)
const plural=(n,one,many)=>`${n} ${n===1?one:many}`
const reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches
function toListings(){document.getElementById('broker-listings')?.scrollIntoView({behavior:reduced()?'auto':'smooth'})}
function showCommune(name){commune.value=name;toListings()}
async function share(){const url=new URL(`api/v1/public/broker-share/${props.slug}`,location.href).href;if(navigator.share){try{await navigator.share({title:profile.value.name,url})}catch{}return}try{await navigator.clipboard.writeText(url);emit('notify','Enlace copiado. En WhatsApp se verá con la portada de la corredora.')}catch{emit('notify',url)}}
</script>
<template>
<section class="broker-hero" :class="{loading:!profile}">
 <img class="broker-cover" :src="cover" alt="" fetchpriority="high">
 <header class="public-header"><a class="brand" href="#portal" @click.prevent="emit('navigate','portal')"><img class="brand-logo" :src="logoLight" alt="Panorama Inmobiliario" width="186" height="44"></a><nav aria-label="Navegación principal"><button class="text-button" @click="emit('navigate','portal')">Todas las propiedades</button><button class="text-button plans-nav" @click="emit('navigate','plans')">Planes</button><button class="button primary" @click="emit('navigate','overview')">Soy corredor<Icon name="arrow"/></button></nav></header>
 <div v-if="profile" class="broker-hero-body">
  <span class="broker-hero-logo"><img v-if="profile.logoUrl" :src="profile.logoUrl" alt="" @error="$event.target.style.display='none'"><span v-else aria-hidden="true">{{initials}}</span></span>
  <div class="broker-hero-copy">
   <p class="broker-kicker">Corredora de propiedades<template v-if="office"> · {{office}}</template></p>
   <h1>{{profile.name}}</h1>
   <p v-if="profile.tagline" class="broker-tagline">{{profile.tagline}}</p>
   <div class="broker-actions"><button class="button gold" @click="toListings">Ver sus propiedades<Icon name="arrow"/></button><a v-if="whatsappUrl" class="button on-dark" :href="whatsappUrl" target="_blank" rel="noopener"><Icon name="chat"/>Escribir por WhatsApp</a><button class="button on-dark" @click="share"><Icon name="share"/>Compartir</button></div>
  </div>
 </div>
 <div v-else-if="missing" class="broker-hero-body"><div class="broker-hero-copy"><h1>No encontramos esta corredora.</h1><p class="broker-tagline">Puede que haya cambiado la dirección de su página o que ya no publique en Panorama.</p><div class="broker-actions"><button class="button gold" @click="emit('navigate','portal')">Ver todas las propiedades<Icon name="arrow"/></button></div></div></div>
 <div v-else class="broker-hero-body" aria-busy="true"><span class="broker-hero-logo skeleton"></span><div class="broker-hero-copy"><span class="skeleton-line wide"></span><span class="skeleton-line"></span></div></div>
</section>
<template v-if="profile">
<ul class="broker-facts" aria-label="Datos de la corredora">
 <li><strong>{{stats.published}}</strong>{{stats.published===1?'propiedad publicada':'propiedades publicadas'}}</li>
 <li><strong>{{stats.sale}}</strong>en venta</li>
 <li><strong>{{stats.rent}}</strong>en arriendo</li>
 <li><strong>{{stats.communes.length}}</strong>{{stats.communes.length===1?'comuna':'comunas'}} con avisos</li>
 <li class="broker-since">En Panorama desde {{since}}</li>
</ul>
<div class="broker-body">
 <div class="broker-main">
  <section v-if="profile.presentation" aria-labelledby="broker-about"><h2 id="broker-about">Sobre {{profile.name}}</h2><p class="broker-presentation">{{profile.presentation}}</p></section>
  <section v-if="stats.communes.length" aria-labelledby="broker-where"><h2 id="broker-where">Dónde tiene propiedades</h2><div class="broker-communes"><button v-for="c in stats.communes" :key="c.commune" class="chip" @click="showCommune(c.commune)">{{c.commune}}<span>{{c.count}}</span></button></div></section>
 </div>
 <aside class="broker-contact" aria-labelledby="broker-contact-title">
  <h2 id="broker-contact-title">Contacto</h2>
  <ul v-if="hasContact" class="contact-list">
   <li v-if="profile.whatsapp"><Icon name="chat"/><a :href="whatsappUrl" target="_blank" rel="noopener">{{profile.whatsapp}}<small>WhatsApp</small></a></li>
   <li v-if="profile.phone"><Icon name="phone"/><a :href="'tel:'+profile.phone.replace(/[^0-9+]/g,'')">{{profile.phone}}<small>Teléfono</small></a></li>
   <li v-if="profile.email"><Icon name="mail"/><a :href="'mailto:'+profile.email">{{profile.email}}<small>Correo</small></a></li>
   <li v-if="profile.website"><Icon name="globe"/><a :href="profile.website" target="_blank" rel="noopener">{{website}}<small>Sitio web</small></a></li>
   <li v-if="profile.hours"><Icon name="clock"/><span>{{profile.hours}}<small>Horario de atención</small></span></li>
   <li v-if="office"><Icon name="pin"/><span>{{office}}<small>Oficina</small></span></li>
  </ul>
  <p v-else class="contact-empty">Esta corredora aún no publica sus datos de contacto. Puedes escribirle desde cualquiera de sus avisos con «Consultar por esta propiedad».</p>
  <template v-if="profile.coverage.length"><h3>Zonas donde trabaja</h3><ul class="coverage-list"><li v-for="c in profile.coverage" :key="c">{{c}}</li></ul><small class="coverage-note">Declaradas por la corredora.</small></template>
 </aside>
</div>
<section id="broker-listings" class="broker-listings" aria-labelledby="broker-listings-title">
 <div class="section-heading"><div><h2 id="broker-listings-title">Su cartera</h2><p class="result-count" aria-live="polite">{{plural(shown.length,'propiedad','propiedades')}}<template v-if="shown.length!==mine.length"> de {{mine.length}}</template></p></div></div>
 <div class="broker-filters">
  <div class="segmented" role="group" aria-label="Operación"><button :aria-pressed="!operation" @click="operation=''">Todas</button><button :aria-pressed="operation==='Venta'" @click="operation='Venta'">Venta <span>{{stats.sale}}</span></button><button :aria-pressed="operation==='Arriendo'" @click="operation='Arriendo'">Arriendo <span>{{stats.rent}}</span></button></div>
  <select v-model="type" aria-label="Tipo de propiedad"><option value="">Todos los tipos</option><option v-for="t in types" :key="t">{{t}}</option></select>
  <select v-model="commune" aria-label="Comuna"><option value="">Todas las comunas</option><option v-for="c in stats.communes" :key="c.commune" :value="c.commune">{{c.commune}} ({{c.count}})</option></select>
 </div>
 <div class="property-grid"><PropertyCard v-for="p in shown" :key="p.id" :property="p" :favorite="favorites.includes(p.id)" @favorite="emit('favorite',$event)" @open="emit('open',$event)"/></div>
 <div v-if="!shown.length" class="empty"><Icon name="search"/><h3>{{mine.length?'Ninguna propiedad coincide con estos filtros.':'Esta corredora aún no tiene propiedades publicadas.'}}</h3><button v-if="mine.length" class="button secondary" @click="operation='';type='';commune=''">Quitar filtros</button></div>
</section>
</template>
</template>
