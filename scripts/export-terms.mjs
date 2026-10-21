import {readFileSync,writeFileSync} from 'node:fs'
const terms=JSON.parse(readFileSync(new URL('../src/data/terms.json',import.meta.url),'utf8'))
const content=`# ${terms.title}\n\nVersión ${terms.version} · ${terms.updated}\n\n**${terms.status}. No vigente; sin aceptación ni contratación habilitadas.**\n\n`+terms.sections.map((s,i)=>`## ${i+1}. ${s.title}\n\n${s.paragraphs.join('\n\n')}`).join('\n\n')+'\n'
writeFileSync(new URL('../docs/TERMINOS_Y_CONDICIONES.md',import.meta.url),content)
