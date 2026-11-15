export default function AsyncView({ loading, error, onRetry, children }) {
  if (loading) return <p className="muted">Memuat...</p>

  if (error) {
    return (
      <div className="alert alert-error">
        <p>{error.message}</p>
        <button type="button" onClick={onRetry}>Coba lagi</button>
      </div>
    )
  }

  return children
}
