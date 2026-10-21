/** Browser client. Session cookies are HttpOnly; CSRF stays in memory. */
export class PanoramaApiError extends Error {
  constructor(status, payload) {
    super(payload?.error?.message || `La API respondió ${status}`)
    this.status = status
    this.code = payload?.error?.code || 'HTTP_ERROR'
    this.details = payload?.error?.details
    this.requestId = payload?.error?.requestId
  }
}
export class PanoramaApi {
  constructor(base = '/api/v1', fetcher = globalThis.fetch.bind(globalThis)) {
    this.base = base.replace(/\/$/, '')
    this.fetcher = fetcher
    this.csrf = null
  }
  async request(method, path, body, { query = {}, signal, headers = {} } = {}) {
    if (!path.startsWith('/') || path.startsWith('//')) throw new Error('Ruta de API inválida')
    const params = new URLSearchParams(Object.entries(query).filter(([, v]) => v !== '' && v != null))
    const response = await this.fetcher(this.base + path + (params.size ? `?${params}` : ''), {
      method, credentials: 'include', signal,
      headers: { ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(this.csrf && !['GET', 'HEAD', 'OPTIONS'].includes(method) ? { 'X-CSRF-Token': this.csrf } : {}), ...headers },
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    })
    const json = (response.headers.get('content-type') || '').includes('application/json')
    const payload = response.status === 204 ? null : json ? await response.json() : await response.blob()
    if (!response.ok) {
      if (response.status === 401) this.csrf = null
      throw new PanoramaApiError(response.status, payload)
    }
    if (payload?.csrfToken) this.csrf = payload.csrfToken
    return payload
  }
  session() { return this.request('GET', '/auth/session') }
  login(email, password) { return this.request('POST', '/auth/login', { email, password }) }
  async logout() { await this.request('POST', '/auth/logout', {}); this.csrf = null }
  // Superadmin de plataforma: usar una instancia separada del cliente (CSRF propio).
  platformLogin(email, password) { return this.request('POST', '/platform/auth/login', { email, password }) }
  platformSession() { return this.request('GET', '/platform/auth/session') }
  async platformLogout() { await this.request('POST', '/platform/auth/logout', {}); this.csrf = null }
  platformOrganizations() { return this.request('GET', '/platform/organizations') }
  platformCreateOrganization(account) { return this.request('POST', '/platform/organizations', account) }
  platformCapacity(id, { limit, status, reason }) { return this.request('PUT', `/platform/organizations/${encodeURIComponent(id)}/subscription`, { limit, status, reason }) }
  platformChanges(id) { return this.request('GET', `/platform/organizations/${encodeURIComponent(id)}/changes`) }
  publicConfig() { return this.request('GET', '/public/config') }
  inquire(propertyId, body) { return this.request('POST', `/public/properties/${encodeURIComponent(propertyId)}/inquiries`, { website: '', ...body }) }
  leads(query = {}) { return this.request('GET', '/leads', undefined, { query }) }
  updateLead(id, { stage, note, version }) { return this.request('PUT', `/leads/${encodeURIComponent(id)}`, { stage, note, version }) }
  visits(query = {}) { return this.request('GET', '/visits', undefined, { query }) }
  createVisit(visit) { return this.request('POST', '/visits', visit) }
  cancelVisit(id, version) { return this.request('DELETE', `/visits/${encodeURIComponent(id)}`, { version }) }
  subscription() { return this.request('GET', '/workspace/subscription') }
  profile() { return this.request('GET', '/workspace/profile') }
  saveProfile(profile) { const { saved, name, joinedAt, coverageSource, ...body } = profile; return this.request('PUT', '/workspace/profile', body) }
  brokerPage(slug) { return this.request('GET', `/public/brokers/${encodeURIComponent(slug)}`) }
  properties(query = {}, isPublic = false) { return this.request('GET', `${isPublic ? '/public' : ''}/properties`, undefined, { query }) }
  saveProperty(property) {
    const { id, brand, createdAt, updatedAt, ...body } = property
    if (!id) delete body.version
    return this.request(id ? 'PUT' : 'POST', `/properties${id ? `/${encodeURIComponent(id)}` : ''}`, body)
  }
  removeProperty(id, version) { return this.request('DELETE', `/properties/${encodeURIComponent(id)}`, { version }) }
  uploadImage(file) {
    return new Promise((resolve, reject) => {
      if (file.size > 4194304) { reject(new Error('La imagen admite hasta 4 MiB')); return }
      const reader = new FileReader()
      reader.onerror = () => reject(new Error('No se pudo leer la imagen'))
      reader.onload = () => resolve(this.request('POST', '/media', { contentBase64: reader.result.split(',')[1] }))
      reader.readAsDataURL(file)
    })
  }
}
