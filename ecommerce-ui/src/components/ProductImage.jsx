import { useState } from 'react'

function getImageSource(image) {
  if (typeof image !== 'string' || !image.trim()) return null
  const value = image.trim()
  if (value.startsWith('data:image/')) return value

  // Product Service menyimpan Base64 mentah; kenali format dari signature-nya.
  let mime = null
  if (value.startsWith('/9j/')) mime = 'image/jpeg'
  else if (value.startsWith('iVBORw0KGgo')) mime = 'image/png'
  else if (value.startsWith('R0lGOD')) mime = 'image/gif'
  else if (value.startsWith('UklGR')) mime = 'image/webp'

  return mime ? `data:${mime};base64,${value}` : null
}

export default function ProductImage({ image, alt = 'Foto produk', className = '' }) {
  const [failed, setFailed] = useState(false)
  const source = getImageSource(image)

  if (!source || failed) {
    return (
      <div className={`product-image-placeholder ${className}`} role="img" aria-label={`Foto ${alt} tidak tersedia`}>
        <span aria-hidden="true">▧</span>
        <small>Foto tidak tersedia</small>
      </div>
    )
  }

  return (
    <img
      className={`product-image ${className}`}
      src={source}
      alt={alt}
      loading="lazy"
      onError={() => setFailed(true)}
      onLoad={(event) => {
        if (event.currentTarget.naturalWidth <= 1 || event.currentTarget.naturalHeight <= 1) setFailed(true)
      }}
    />
  )
}

