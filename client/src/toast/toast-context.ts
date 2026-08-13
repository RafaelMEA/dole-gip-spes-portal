import { createContext } from "react"

export type ToastVariant = "success" | "error" | "info"

export interface ToastOptions {
  title: string
  description?: string
  variant?: ToastVariant
  duration?: number
}

export interface ToastContextValue {
  toast: (options: ToastOptions) => void
}

export const ToastContext = createContext<ToastContextValue | null>(null)
