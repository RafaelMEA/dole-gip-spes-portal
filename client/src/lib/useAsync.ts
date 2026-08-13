import { useCallback, useEffect, useState } from "react"
import { ApiError } from "@/lib/api"

interface UseAsyncState<T> {
  data: T | null
  loading: boolean
  error: ApiError | null
}

function normalizeError(err: unknown): ApiError {
  return err instanceof ApiError ? err : new ApiError("An unexpected error occurred.", 0)
}

export function useAsync<T>(fetcher: () => Promise<T>) {
  const [state, setState] = useState<UseAsyncState<T>>({ data: null, loading: true, error: null })

  const run = useCallback(() => {
    let active = true
    fetcher()
      .then((data) => {
        if (active) setState({ data, loading: false, error: null })
      })
      .catch((err: unknown) => {
        if (active) setState({ data: null, loading: false, error: normalizeError(err) })
      })
    return () => {
      active = false
    }
  }, [fetcher])

  useEffect(() => run(), [run])

  const reload = useCallback(() => {
    setState((current) => ({ ...current, loading: true }))
    return run()
  }, [run])

  return { ...state, reload }
}
