<script setup>
import {ref,computed} from 'vue'
import Icon from './Icon.vue'
import ProfileEditor from './ProfileEditor.vue'
const props=defineProps({properties:Array})
const emit=defineEmits(['saved','notify'])
const counts=computed(()=>Object.fromEntries(['Publicada','Borrador','Pausada','Vendida','Arrendada'].map(status=>[status,props.properties.filter(p=>p.status===status).length])))
function exportReport(){
 const safe=value=>{let text=String(value??'');if(/^[\s]*[=+@-]/.test(text))text="'"+text;return '"'+text.replaceAll('"','""')+'"'}
 const rows=[['Código','Propiedad','Comuna','Operación','Estado','Moneda','Precio'],...props.properties.map(p=>[p.id,p.title,p.commune,p.operation,p.status,p.currency,p.price])]
 const blob=new Blob(['\uFEFF'+rows.map(row=>row.map(safe).join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'})
 const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='Panorama-reporte-publicaciones.csv';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000)
}
</script>
<template><section class="benefits-page"><ProfileEditor @saved="emit('saved',$event)" @notify="emit('notify',$event)"/><section class="panel"><div class="panel-heading"><div><h3>Reporte de tus publicaciones</h3><p>Fotografía actual de tu cartera. No representa visitas, rendimiento histórico ni estadísticas trimestrales.</p></div><button class="button secondary" @click="exportReport">Descargar reporte CSV<Icon name="external"/></button></div><div class="report-states"><div v-for="(count,status) in counts" :key="status"><strong>{{count}}</strong><span>{{status}}</span></div></div></section><div class="benefits-upcoming"><section class="panel"><span class="eyebrow">BENEFICIO PREVISTO · AÚN NO DISPONIBLE</span><h3>Informes trimestrales</h3><p>Previstos con evolución de cartera, consultas, visitas y acciones comerciales. Requieren registros históricos verificables; no se generarán resultados retroactivos ficticios.</p></section><section class="panel"><span class="eyebrow">BENEFICIO PREVISTO · AÚN NO DISPONIBLE</span><h3>Capacitaciones y webinars</h3><p>Programa propuesto sobre publicaciones, fotografía inmobiliaria, atención de interesados y uso del panel. Fechas, cupos, modalidad y acceso a grabaciones se informarán cuando exista un calendario confirmado.</p></section></div></section></template>
