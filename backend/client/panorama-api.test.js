import test from 'node:test'
import assert from 'node:assert/strict'
import { PanoramaApi, PanoramaApiError } from './panorama-api.js'
function reply(status, data) { return { status, ok: status < 400, headers: new Headers({ 'content-type': 'application/json' }), json: async () => data } }
test('cookie incluida, CSRF en memoria y campos de lectura excluidos al guardar', async () => {
  const calls = []
  const api = new PanoramaApi('/api/v1', async (url, options) => { calls.push([url, options]); return reply(200, calls.length === 1 ? { csrfToken: 'secret' } : {}) })
  await api.session()
  await api.saveProperty({ id: 'id', title: 'Casa', version: 2, brand: { name: 'Marca' }, createdAt: 'fecha' })
  assert.equal(calls[1][1].credentials, 'include')
  assert.equal(calls[1][1].headers['X-CSRF-Token'], 'secret')
  assert.deepEqual(JSON.parse(calls[1][1].body), { title: 'Casa', version: 2 })
})
test('errores conservan código y 401 elimina CSRF sin repetir escritura', async () => {
  let count = 0
  const api = new PanoramaApi('/api/v1', async () => { count++; return reply(401, { error: { code: 'AUTH_REQUIRED', message: 'Inicia sesión', requestId: 'trace' } }) })
  api.csrf = 'expired'
  await assert.rejects(() => api.request('POST', '/properties', {}), e => e instanceof PanoramaApiError && e.code === 'AUTH_REQUIRED' && e.requestId === 'trace')
  assert.equal(api.csrf, null)
  assert.equal(count, 1)
})
