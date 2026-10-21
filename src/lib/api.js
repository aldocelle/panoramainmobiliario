import { PanoramaApi } from '../../backend/client/panorama-api.js'
export { PanoramaApiError } from '../../backend/client/panorama-api.js'
// Ruta relativa: funciona igual si el sitio vive en la raíz de un subdominio o en una subcarpeta (/panorama/).
export const api = new PanoramaApi(import.meta.env.VITE_API_BASE || 'api/v1')
// Cliente aparte para el superadmin: su token CSRF no debe pisar el de la sesión de corredora.
export const platformApi = new PanoramaApi(import.meta.env.VITE_API_BASE || 'api/v1')
