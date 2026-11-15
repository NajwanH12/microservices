import { useEffect, useState } from 'react'

export function useAsync(asyncFn, deps = []) {
  const [reloadCount, setReloadCount] = useState(0)
  const [result, setResult] = useState({ key: null })
  const key = `${JSON.stringify(deps)}:${reloadCount}`

  useEffect(() => {
    let cancelled = false

    asyncFn()
      .then((data) => {
        if (!cancelled) setResult({ key, data, error: null })
      })
      .catch((error) => {
        if (!cancelled) setResult({ key, data: null, error })
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key])

  const loading = result.key !== key

  return {
    loading,
    data: loading ? null : result.data,
    error: loading ? null : result.error,
    reload: () => setReloadCount((count) => count + 1),
  }
}
