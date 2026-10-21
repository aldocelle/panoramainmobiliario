import { PanoramaApi } from '../../backend/client/panorama-api.js'
export { PanoramaApiError } from '../../backend/client/panorama-api.js'
export const api = new PanoramaApi(import.meta.env.VITE_API_BASE || '/api/v1')
