const API_URL: string = (import.meta.env.VITE_API_URL as string | undefined) ?? "http://localhost:8000"

export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]> | undefined

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = "ApiError"
    this.status = status
    this.errors = errors
  }
}

interface ApiResponseBody {
  message?: string
  errors?: Record<string, string[]>
}

const DEFAULT_ERRORS: Record<number, string> = {
  401: "Your session has expired. Please sign in again.",
  403: "You are not authorized to perform this action.",
  404: "The requested resource was not found.",
  419: "Your session has expired. Please refresh and try again.",
  429: "Too many attempts. Please try again later.",
}

function getCookie(name: string): string | null {
  const prefix = `${name}=`
  for (const cookie of document.cookie.split(";")) {
    const trimmed = cookie.trim()
    if (trimmed.startsWith(prefix)) {
      return decodeURIComponent(trimmed.slice(prefix.length))
    }
  }
  return null
}

function errorMessage(status: number, payload: ApiResponseBody | null): string {
  if (payload?.errors) {
    const firstFieldError = Object.values(payload.errors)[0]?.[0]
    if (firstFieldError) {
      return firstFieldError
    }
  }
  if (payload?.message) {
    return payload.message
  }
  return DEFAULT_ERRORS[status] ?? "An unexpected error occurred. Please try again later."
}

async function readBody(response: Response): Promise<ApiResponseBody | null> {
  const contentType = response.headers.get("Content-Type") ?? ""
  if (!contentType.includes("application/json")) {
    return null
  }
  return (await response.json().catch(() => null)) as ApiResponseBody | null
}

export async function requestCsrfCookie(): Promise<void> {
  await fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: "include" })
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set("Accept", "application/json")
  if (options.body && !(options.body instanceof FormData) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json")
  }

  const xsrfToken = getCookie("XSRF-TOKEN")
  if (xsrfToken) {
    headers.set("X-XSRF-TOKEN", xsrfToken)
  }

  let response: Response
  try {
    response = await fetch(`${API_URL}${path}`, { ...options, headers, credentials: "include" })
  } catch {
    throw new ApiError("Unable to reach the server. Please check your connection.", 0)
  }

  const payload = await readBody(response)

  if (!response.ok) {
    throw new ApiError(errorMessage(response.status, payload), response.status, payload?.errors)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return payload as T
}

/**
 * Upload a file via XMLHttpRequest so progress can be reported while the
 * browser pushes bytes to the server. Cookie credentials and the XSRF token
 * are sent exactly like the regular fetch-based helper.
 */
export async function apiUpload<T>(
  path: string,
  formData: FormData,
  onProgress?: (percent: number) => void,
): Promise<T> {
  const xsrfToken = getCookie("XSRF-TOKEN")

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open("POST", `${API_URL}${path}`)
    xhr.withCredentials = true
    xhr.responseType = "json"
    xhr.setRequestHeader("Accept", "application/json")
    if (xsrfToken) {
      xhr.setRequestHeader("X-XSRF-TOKEN", xsrfToken)
    }

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable && onProgress) {
        onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)))
      }
    }

    xhr.onerror = () =>
      reject(new ApiError("Unable to reach the server. Please check your connection.", 0))
    xhr.onabort = () => reject(new ApiError("The upload was cancelled.", 0))

    xhr.onload = () => {
      const payload = (xhr.response as ApiResponseBody | null) ?? null
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve((payload ?? {}) as T)
      } else {
        reject(new ApiError(errorMessage(xhr.status, payload), xhr.status, payload?.errors))
      }
    }

    xhr.send(formData)
  })
}
