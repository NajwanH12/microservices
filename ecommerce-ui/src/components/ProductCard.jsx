import { Link } from 'react-router-dom'
import { formatRupiah } from '../utils/format'
import StarRating from './StarRating'
import ProductImage from './ProductImage'

export default function ProductCard({ product }) {
  const { id, name, price, stock, rating, image } = product

  return (
    <article className="card">
      <Link className="product-card-image-link" to={`/products/${id}`} aria-label={`Lihat ${name}`}>
        <ProductImage image={image} alt={name} className="product-card-image" />
      </Link>
      <h3><Link to={`/products/${id}`}>{name}</Link></h3>
      <p className="price">{formatRupiah(price)}</p>
      <p className="muted">Stok: {stock}</p>
      {rating ? (
        <StarRating average={rating.average_rating} total={rating.total_reviews} />
      ) : (
        <span className="muted">Rating tidak tersedia</span>
      )}
    </article>
  )
}
