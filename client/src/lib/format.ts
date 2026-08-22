export function formatDate(value: string | null | undefined): string {
  if (!value) return "—"
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return "—"
  return date.toLocaleDateString("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  })
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—"
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return "—"
  return date.toLocaleString("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  })
}

export function formatFileSize(bytes: number | null | undefined): string {
  if (!bytes) return "—"
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function formatMaxUploadSize(kb: number | null | undefined): string {
  if (!kb) return "—"
  if (kb >= 1024) return `${+(kb / 1024).toFixed(1)} MB`
  return `${kb} KB`
}

export function formatRelative(value: string | null | undefined): string {
  if (!value) return "—"
  const date = new Date(value)
  const diffMs = date.getTime() - Date.now()
  const absDays = Math.round(Math.abs(diffMs) / 86_400_000)
  const future = diffMs > 0
  if (absDays === 0) return "today"
  if (absDays === 1) return future ? "tomorrow" : "yesterday"
  if (absDays < 7) return `${future ? "in " : ""}${absDays} day${absDays > 1 ? "s" : ""}${future ? "" : " ago"}`
  return formatDate(value)
}

/**
 * Badge text for an unread-notification count: null hides the badge,
 * anything above 99 collapses to "99+".
 */
export function formatBadgeCount(count: number): string | null {
  if (count <= 0) return null
  if (count > 99) return "99+"
  return String(count)
}

export function formatRelativeTime(value: string | null | undefined): string {
  if (!value) return "—"
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return "—"

  const secondsAgo = Math.floor((Date.now() - date.getTime()) / 1000)

  if (secondsAgo < 60) return "Just now"
  if (secondsAgo < 3_600) {
    const minutes = Math.floor(secondsAgo / 60)
    return `${minutes} minute${minutes === 1 ? "" : "s"} ago`
  }
  if (secondsAgo < 86_400) {
    const hours = Math.floor(secondsAgo / 3_600)
    return `${hours} hour${hours === 1 ? "" : "s"} ago`
  }
  if (secondsAgo < 172_800) return "Yesterday"
  if (secondsAgo < 7 * 86_400) {
    const days = Math.floor(secondsAgo / 86_400)
    return `${days} days ago`
  }
  return formatDateTime(value)
}
