// Prepara una foto en el navegador antes de subirla: respeta la orientación de la cámara,
// limita el lado mayor y recomprime. Así las fotos de celular (12 MP, 5-10 MB) no se rechazan
// por tamaño y se sube menos. El servidor vuelve a optimizar y genera la miniatura.
export const UPLOAD_MAX_SIDE = 2560
const MAX_BYTES = 3.5 * 1024 * 1024

export function targetSize(width, height, max = UPLOAD_MAX_SIDE) {
  const ratio = Math.min(1, max / Math.max(width, height))
  return { width: Math.round(width * ratio), height: Math.round(height * ratio) }
}

export async function prepareImage(file) {
  if (typeof createImageBitmap !== 'function') return file
  let bitmap
  try { bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' }) } catch { return file }
  const { width, height } = targetSize(bitmap.width, bitmap.height)
  const fits = width === bitmap.width && file.size <= MAX_BYTES && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
  if (fits) { bitmap.close(); return file }
  const canvas = document.createElement('canvas')
  canvas.width = width; canvas.height = height
  canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height)
  bitmap.close()
  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', 0.88))
  return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.webp', { type: 'image/webp' }) : file
}
